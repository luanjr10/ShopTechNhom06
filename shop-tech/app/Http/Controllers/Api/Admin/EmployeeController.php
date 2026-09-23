<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeePermission;
use App\Models\User;
use App\Rules\VietnamesePhone;
use App\Support\AdminModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quản lý nhân viên (role=employee) + phân quyền theo module — CHỈ role=admin
 * thật mới vào được các route này (xem routes/api/admin.php, không bọc bởi
 * middleware `permission:` như các module khác — tránh nhân viên tự cấp
 * quyền leo thang cho chính mình).
 */
class EmployeeController extends Controller
{
    // [GET] /api/admin/employees
    public function index(Request $request)
    {
        $query = User::where('role', 'employee');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->latest();

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    // [GET] /api/admin/employees/{employee}
    public function show(User $employee)
    {
        abort_unless($employee->role === 'employee', 404, 'Không tìm thấy nhân viên');

        return response()->json([
            'success' => true,
            'data' => [
                ...$employee->load('permissions')->toArray(),
                'permission_modules' => $this->mergedPermissions($employee),
            ],
        ], 200);
    }

    // [POST] /api/admin/employees — mật khẩu mặc định "password", nhân viên tự đổi sau.
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email:rfc,dns|max:150|unique:users,email',
            'phone' => ['nullable', 'string', new VietnamesePhone],
        ]);

        $employee = User::create([
            ...$validated,
            'role' => 'employee',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo nhân viên — mật khẩu mặc định: password',
            'data' => $employee,
        ], 201);
    }

    // [PATCH] /api/admin/employees/{employee}
    public function update(Request $request, User $employee)
    {
        abort_unless($employee->role === 'employee', 404, 'Không tìm thấy nhân viên');

        // Chỉ tra DNS khi email thay đổi — không chặn việc sửa các trường khác
        // nếu email hiện tại của nhân viên trót không còn resolve được.
        $emailChanged = $request->input('email') !== $employee->email;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($employee->id)],
            'email' => [
                'required', $emailChanged ? 'email:rfc,dns' : 'email:rfc', 'max:150',
                Rule::unique('users', 'email')->ignore($employee->id),
            ],
            'phone' => ['nullable', 'string', new VietnamesePhone],
        ]);

        $employee->update($validated);

        return response()->json(['success' => true, 'message' => 'Đã cập nhật nhân viên', 'data' => $employee], 200);
    }

    // [DELETE] /api/admin/employees/{employee}
    public function destroy(Request $request, User $employee)
    {
        abort_unless($employee->role === 'employee', 404, 'Không tìm thấy nhân viên');

        if ($employee->id === $request->user()->id) {
            throw ValidationException::withMessages(['employee' => 'Không thể tự xoá chính mình.']);
        }

        $employee->delete();

        return response()->json(['success' => true, 'message' => 'Đã xoá nhân viên'], 200);
    }

    // [GET] /api/admin/permission-modules — danh sách module + abilities chuẩn (render lưới checkbox).
    public function modules()
    {
        return response()->json(['success' => true, 'data' => AdminModules::list()], 200);
    }

    // [PUT] /api/admin/employees/{employee}/permissions
    public function updatePermissions(Request $request, User $employee)
    {
        abort_unless($employee->role === 'employee', 404, 'Không tìm thấy nhân viên');

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.module' => ['required', 'string', Rule::in(AdminModules::keys())],
            'permissions.*.can_view' => 'boolean',
            'permissions.*.can_create' => 'boolean',
            'permissions.*.can_edit' => 'boolean',
            'permissions.*.can_delete' => 'boolean',
        ]);

        foreach ($validated['permissions'] as $item) {
            EmployeePermission::updateOrCreate(
                ['user_id' => $employee->id, 'module' => $item['module']],
                [
                    'can_view' => $item['can_view'] ?? false,
                    'can_create' => $item['can_create'] ?? false,
                    'can_edit' => $item['can_edit'] ?? false,
                    'can_delete' => $item['can_delete'] ?? false,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật phân quyền',
            'data' => $this->mergedPermissions($employee->fresh()),
        ], 200);
    }

    /**
     * Trộn danh sách module CHUẨN với quyền hiện có của nhân viên — module
     * chưa có dòng nào trong DB vẫn hiện ra (mặc định false) để FE render đủ
     * lưới checkbox ngay cả với nhân viên hoàn toàn mới.
     */
    private function mergedPermissions(User $employee): array
    {
        $existing = $employee->permissions()->get()->keyBy('module');

        return collect(AdminModules::list())->map(function ($module) use ($existing) {
            $perm = $existing->get($module['key']);

            return [
                ...$module,
                'can_view' => (bool) ($perm?->can_view ?? false),
                'can_create' => (bool) ($perm?->can_create ?? false),
                'can_edit' => (bool) ($perm?->can_edit ?? false),
                'can_delete' => (bool) ($perm?->can_delete ?? false),
            ];
        })->values()->all();
    }
}
