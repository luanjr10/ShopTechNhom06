import api, { ensureCsrf } from "../api/axios";
import type { SellerProfile } from "../types/seller.types";

/** Quyền của 1 nhân viên trên 1 module — xem App\Support\AdminModules ở BE. */
export interface EmployeePermission {
  module: string;
  can_view: boolean;
  can_create: boolean;
  can_edit: boolean;
  can_delete: boolean;
}

export interface AuthUser {
  id: number;
  name: string;
  username: string;
  email: string;
  email_verified_at?: string | null;
  phone?: string | null;
  avatar?: string | null;
  avatar_url?: string | null;
  /** false với tài khoản tạo qua Google (chưa từng đặt mật khẩu). */
  has_password?: boolean;
  role: "customer" | "seller" | "admin" | "employee";
  seller_profile?: SellerProfile | null;
  /** Chỉ có ý nghĩa với role=employee — admin thật luôn full quyền (không cần mảng này). */
  permissions?: EmployeePermission[];
}

/** true nếu user (admin luôn true, employee tra theo permissions) có quyền `ability` trên `module`. */
export function hasPermission(
  user: AuthUser | null | undefined,
  module: string,
  ability: "view" | "create" | "edit" | "delete",
): boolean {
  if (!user) return false;
  if (user.role === "admin") return true;
  if (user.role !== "employee") return false;
  const perm = user.permissions?.find((p) => p.module === module);
  return !!perm?.[`can_${ability}` as const];
}

export async function login(
  loginId: string,
  password: string,
): Promise<AuthUser> {
  await ensureCsrf();
  // AuthController@login trả { data: { user, access_token, token_type } },
  // KHÔNG phải user phẳng như /me — phải lấy .user, không phải .data thẳng.
  const res = await api.post("/login", { login: loginId, password });
  return res.data.data.user as AuthUser;
}

export async function me(): Promise<AuthUser> {
  const res = await api.get("/me");
  return res.data.data as AuthUser;
}

export async function logout(): Promise<void> {
  await api.post("/logout");
}
