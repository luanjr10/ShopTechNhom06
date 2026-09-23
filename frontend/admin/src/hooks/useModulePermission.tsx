import { useAuth } from "../context/AuthContext";
import { hasPermission } from "../services/auth.services";

/**
 * Quyền của user hiện tại trên 1 module quản lý — admin thật luôn full quyền,
 * nhân viên (role=employee) tra theo EmployeePermission. Dùng để ẩn nút
 * Thêm/Sửa/Xóa trên các trang quản lý khi nhân viên không có quyền tương ứng
 * (trang vẫn vào được nếu có quyền "view", chỉ ẩn thao tác không được phép).
 */
export function useModulePermission(module: string) {
  const { user } = useAuth();

  return {
    canView: hasPermission(user, module, "view"),
    canCreate: hasPermission(user, module, "create"),
    canEdit: hasPermission(user, module, "edit"),
    canDelete: hasPermission(user, module, "delete"),
  };
}
