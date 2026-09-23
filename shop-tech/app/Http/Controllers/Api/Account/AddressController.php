<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAddressRequest;
use App\Http\Requests\Account\UpdateAddressRequest;
use App\Models\Address;
use App\Services\LocationService;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function __construct(private LocationService $locationService) {}

    /**
     * [GET] /api/addresses — danh sách địa chỉ của người dùng (mặc định lên đầu).
     */
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses,
        ]);
    }

    /**
     * [POST] /api/addresses — thêm địa chỉ mới.
     */
    public function store(StoreAddressRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $location = $this->resolveLocation((int) $data['province_id'], (int) $data['district_id'], (string) $data['ward_code']);
        if ($location === null) {
            return response()->json([
                'success' => false,
                'message' => 'Quận/huyện hoặc phường/xã không thuộc tỉnh/thành phố đã chọn, hoặc không tồn tại.',
            ], 422);
        }

        // Địa chỉ đầu tiên luôn là mặc định.
        $makeDefault = ($data['is_default'] ?? false) || $user->addresses()->count() === 0;

        $address = $user->addresses()->create([
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address_line' => $data['address_line'],
            ...$location,
            'is_default' => $makeDefault,
        ]);

        if ($makeDefault) {
            $this->markAsOnlyDefault($request, $address);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm địa chỉ',
            'data' => $address->fresh(),
        ], 201);
    }

    /**
     * [PATCH] /api/addresses/{address} — cập nhật địa chỉ.
     */
    public function update(UpdateAddressRequest $request, Address $address)
    {
        $this->authorizeOwner($request, $address);

        $data = $request->validated();

        if (array_key_exists('province_id', $data) || array_key_exists('district_id', $data) || array_key_exists('ward_code', $data)) {
            $provinceId = (int) ($data['province_id'] ?? $address->province_id_ghn);
            $districtId = (int) ($data['district_id'] ?? $address->district_id);
            $wardCode = (string) ($data['ward_code'] ?? $address->ward_code_ghn);

            $location = $this->resolveLocation($provinceId, $districtId, $wardCode);
            if ($location === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quận/huyện hoặc phường/xã không thuộc tỉnh/thành phố đã chọn, hoặc không tồn tại.',
                ], 422);
            }

            $data = [...$data, ...$location];
        }

        $address->update($data);

        if ($data['is_default'] ?? false) {
            $this->markAsOnlyDefault($request, $address);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật địa chỉ',
            'data' => $address->fresh(),
        ]);
    }

    /**
     * [DELETE] /api/addresses/{address} — xoá địa chỉ.
     */
    public function destroy(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);

        $wasDefault = $address->is_default;
        $address->delete();

        // Nếu vừa xoá địa chỉ mặc định, chọn địa chỉ mới nhất làm mặc định.
        if ($wasDefault) {
            $next = $request->user()->addresses()->latest()->first();
            $next?->update(['is_default' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xoá địa chỉ',
        ]);
    }

    /**
     * [POST] /api/addresses/{address}/default — đặt làm địa chỉ mặc định.
     */
    public function setDefault(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);

        $address->update(['is_default' => true]);
        $this->markAsOnlyDefault($request, $address);

        return response()->json([
            'success' => true,
            'message' => 'Đã đặt làm địa chỉ mặc định',
            'data' => $address->fresh(),
        ]);
    }

    /**
     * Tra cứu tỉnh + quận/huyện + phường/xã theo mã GHN từ nguồn hành chính
     * (KHÔNG tin tên do FE gửi lên) và trả snapshot tên để lưu — dùng hệ CŨ của
     * GHN (có quận/huyện) vì API Tính phí GHN chỉ nhận district_id/ward_code
     * kiểu này. Null nếu không hợp lệ/không khớp nhau.
     *
     * @return array{province_id_ghn:int, province_name_ghn:string, district_id:int, district_name:string, ward_code_ghn:string, ward_name_ghn:string}|null
     */
    private function resolveLocation(int $provinceId, int $districtId, string $wardCode): ?array
    {
        $province = $this->locationService->findProvince($provinceId);
        $district = $province ? $this->locationService->findDistrict($provinceId, $districtId) : null;
        $ward = $district ? $this->locationService->findWard($districtId, $wardCode) : null;

        if (! $province || ! $district || ! $ward) {
            return null;
        }

        return [
            'province_id_ghn' => $provinceId,
            'province_name_ghn' => $province['ProvinceName'],
            'district_id' => $districtId,
            'district_name' => $district['DistrictName'],
            'ward_code_ghn' => $wardCode,
            'ward_name_ghn' => $ward['WardName'],
        ];
    }

    /**
     * Đảm bảo chỉ một địa chỉ là mặc định.
     */
    private function markAsOnlyDefault(Request $request, Address $address): void
    {
        $request->user()->addresses()
            ->where('id', '!=', $address->id)
            ->update(['is_default' => false]);
    }

    /**
     * Chặn thao tác lên địa chỉ không thuộc người dùng hiện tại.
     */
    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403, 'Không có quyền với địa chỉ này');
    }
}
