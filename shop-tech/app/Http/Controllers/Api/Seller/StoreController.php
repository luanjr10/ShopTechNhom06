<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreStoreRequest;
use App\Http\Requests\Seller\UpdateStorePickupAddressRequest;
use App\Models\Store;
use App\Services\CloudinaryService;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Seller quản lý các gian hàng CỦA MÌNH. Chỉ seller approved (có SellerProfile) mới tạo được.
 */
class StoreController extends Controller
{
    // [GET] /api/seller/stores — danh sách store của seller đang đăng nhập.
    public function index(Request $request)
    {
        $profile = $request->user()->sellerProfile;

        return response()->json([
            'success' => true,
            'data' => $profile->stores()->latest()->get(),
        ], 200);
    }

    // [POST] /api/seller/stores
    public function store(StoreStoreRequest $request, CloudinaryService $cloudinaryService)
    {
        $profile = $request->user()->sellerProfile;

        $logoUrl = $request->hasFile('logo')
            ? $cloudinaryService->uploadImage($request->file('logo'))
            : null;

        $store = Store::create([
            'seller_profile_id' => $profile->id,
            'name' => $request->name,
            'slug' => $this->uniqueSlug($request->name),
            'logo' => $logoUrl,
            'description' => $request->description,
            // Gian hàng mới luôn chờ admin duyệt (không cho seller tự đặt trạng thái).
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo gian hàng, đang chờ admin duyệt',
            'data' => $store,
        ], 201);
    }

    // [GET] /api/seller/stores/{store}
    public function show(Store $store)
    {
        return response()->json([
            'success' => true,
            'data' => $store,
        ], 200);
    }

    // [PATCH|POST] /api/seller/stores/{store}
    public function update(StoreStoreRequest $request, Store $store, CloudinaryService $cloudinaryService)
    {
        // Seller không tự đổi trạng thái duyệt — chỉ admin approve/suspend.
        $data = [
            'name' => $request->name,
            'description' => $request->description,
        ];

        if ($request->hasFile('logo')) {
            $data['logo'] = $cloudinaryService->uploadImage($request->file('logo'));
        }

        $store->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật gian hàng thành công',
            'data' => $store->refresh(),
        ], 200);
    }

    // [PUT] /api/seller/stores/{store}/pickup-address — địa chỉ GHN lấy hàng
    // của gian hàng (bắt buộc trước khi "Bàn giao vận chuyển" — xem
    // Store::hasPickupAddress()/SellerOrderService::handover()).
    public function updatePickupAddress(UpdateStorePickupAddressRequest $request, Store $store, LocationService $locationService)
    {
        $data = $request->validated();

        $province = $locationService->findProvince((int) $data['province_id']);
        $district = $province ? $locationService->findDistrict((int) $data['province_id'], (int) $data['district_id']) : null;
        $ward = $district ? $locationService->findWard((int) $data['district_id'], (string) $data['ward_code']) : null;

        if (! $province || ! $district || ! $ward) {
            return response()->json([
                'success' => false,
                'message' => 'Quận/huyện hoặc phường/xã không thuộc tỉnh/thành phố đã chọn, hoặc không tồn tại.',
            ], 422);
        }

        $store->update([
            'pickup_contact_name' => $data['pickup_contact_name'],
            'pickup_phone' => $data['pickup_phone'],
            'address_line' => $data['address_line'],
            'province_id' => $data['province_id'],
            'province_name' => $province['ProvinceName'],
            'district_id' => $data['district_id'],
            'district_name' => $district['DistrictName'],
            'ward_code' => $data['ward_code'],
            'ward_name' => $ward['WardName'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật địa chỉ lấy hàng',
            'data' => $store->refresh(),
        ], 200);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Store::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
