import api, { ensureCsrf } from "../api/axios";
import type { SellerProfile } from "../types/seller.types";

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
  has_password?: boolean;
  role: "customer" | "seller" | "admin" | "employee";
  seller_profile?: SellerProfile | null;
  permissions?: EmployeePermission[];
}

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
