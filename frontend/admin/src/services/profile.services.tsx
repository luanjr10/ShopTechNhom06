import api from "../api/axios";
import type { AuthUser } from "./auth.services";

/**
 * Cài đặt tài khoản (self-service) cho admin/seller/employee — mọi endpoint
 * dưới đây role-agnostic ở backend (dùng chung với app khách hàng qua
 * routes/api/account.php + auth.php), chỉ khác FE gọi qua `api` (axios) thay
 * vì `libs/api.ts` của client.
 */

export interface UpdateProfilePayload {
  name: string;
  username: string;
  email: string;
  phone?: string | null;
}

/** Cập nhật hồ sơ (tên, username, email, sđt). Đổi email sẽ reset xác thực. */
export async function updateProfile(payload: UpdateProfilePayload): Promise<AuthUser> {
  const res = await api.patch("/profile", payload);
  return res.data.data as AuthUser;
}

/** Upload ảnh đại diện. */
export async function uploadAvatar(file: File): Promise<AuthUser> {
  const form = new FormData();
  form.append("avatar", file);
  const res = await api.post("/profile/avatar", form);
  return res.data.data as AuthUser;
}

export interface ChangePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

/** Đổi mật khẩu bằng mật khẩu hiện tại. */
export async function changePassword(payload: ChangePasswordPayload): Promise<string> {
  const res = await api.post("/change-password", payload);
  return res.data.message as string;
}

/** Gửi lại email xác thực cho tài khoản đang đăng nhập. */
export async function resendVerification(): Promise<string> {
  const res = await api.post("/email/verification-notification");
  return res.data.message as string;
}

/** Gửi mã OTP 6 số về email — trả về TTL (giây) mã có hiệu lực. */
export async function forgotPassword(email: string): Promise<number> {
  const res = await api.post("/forgot-password", { email });
  return res.data.ttl ?? 60;
}

export async function verifyResetCode(email: string, code: string): Promise<void> {
  await api.post("/verify-reset-code", { email, code });
}

export interface ResetPasswordPayload {
  email: string;
  code: string;
  password: string;
  password_confirmation: string;
}

/** Đặt mật khẩu mới bằng mã OTP (dùng chung cho "quên mật khẩu" và "đổi mật khẩu qua email"). */
export async function resetPassword(payload: ResetPasswordPayload): Promise<string> {
  const res = await api.post("/reset-password", payload);
  return res.data.message as string;
}
