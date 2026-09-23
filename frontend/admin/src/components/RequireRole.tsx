import { Navigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { hasPermission } from "../services/auth.services";

/**
 * Chặn truy cập nếu chưa đăng nhập, role không nằm trong `roles`, hoặc (khi
 * có `module`) nhân viên (role=employee) chưa được cấp quyền `ability` trên
 * module đó. Đây là chốt chặn ở FE — chốt chặn THẬT nằm ở backend (middleware
 * role + permission:module,ability), FE chỉ để tránh hiện nhầm UI.
 */
export default function RequireRole({
  roles,
  module,
  ability = "view",
  children,
}: {
  roles: Array<"admin" | "seller" | "employee">;
  /** Bắt buộc khi cần kiểm tra thêm quyền module (đa số trang quản lý admin). */
  module?: string;
  ability?: "view" | "create" | "edit" | "delete";
  children: React.ReactNode;
}) {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-50 text-gray-400 dark:bg-gray-900 dark:text-gray-500">
        Đang tải...
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (!roles.includes(user.role as "admin" | "seller" | "employee")) {
    return <Navigate to="/" replace />;
  }

  if (module && !hasPermission(user, module, ability)) {
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
}
