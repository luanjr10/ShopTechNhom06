import { apiAuthGet, apiDelete, apiPatch, apiPost, apiUpload } from "../libs/api";
import { type Address, type AddressPayload, type AuthUser } from "../types/auth";

interface UserResponse {
  success: boolean;
  message: string;
  data: AuthUser;
}

interface MessageResponse {
  success: boolean;
  message: string;
}

export interface UpdateProfilePayload {
  name: string;
  username: string;
  email: string;
  phone?: string | null;
}

/** Cập nhật hồ sơ (tên, username, email, sđt). */
export async function updateProfile(
  payload: UpdateProfilePayload,
): Promise<AuthUser> {
  const res = await apiPatch<UserResponse>("/profile", payload);
  return res.data;
}

/** Upload ảnh đại diện. */
export async function uploadAvatar(file: File): Promise<AuthUser> {
  const form = new FormData();
  form.append("avatar", file);
  const res = await apiUpload<UserResponse>("/profile/avatar", form);
  return res.data;
}

export interface ChangePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

/** Đổi mật khẩu (nhập mật khẩu hiện tại). */
export async function changePassword(
  payload: ChangePasswordPayload,
): Promise<string> {
  const res = await apiPost<MessageResponse>("/change-password", payload);
  return res.message;
}

/** Đăng xuất khỏi tất cả thiết bị khác. */
export async function logoutOtherDevices(): Promise<string> {
  const res = await apiPost<MessageResponse>("/logout-others");
  return res.message;
}

/** Gửi lại email xác thực. */
export async function resendVerification(): Promise<string> {
  const res = await apiPost<MessageResponse>("/email/verification-notification");
  return res.message;
}

// ---- Địa chỉ giao hàng ----

export async function fetchAddresses(): Promise<Address[]> {
  const res = await apiAuthGet<{ data: Address[] }>("/addresses");
  return res.data;
}

export async function createAddress(payload: AddressPayload): Promise<Address> {
  const res = await apiPost<{ data: Address }>("/addresses", payload);
  return res.data;
}

export async function updateAddress(
  id: number,
  payload: AddressPayload,
): Promise<Address> {
  const res = await apiPatch<{ data: Address }>(`/addresses/${id}`, payload);
  return res.data;
}

export async function deleteAddress(id: number): Promise<void> {
  await apiDelete(`/addresses/${id}`);
}

export async function setDefaultAddress(id: number): Promise<Address> {
  const res = await apiPost<{ data: Address }>(`/addresses/${id}/default`);
  return res.data;
}
