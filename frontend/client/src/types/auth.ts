/** Gian hàng của người bán (rút gọn, lấy kèm khi login/me). */
export interface Store {
  id: number;
  name: string;
  slug?: string;
  status: string;
}

/** Hồ sơ người bán gắn với user (nếu đã là seller). */
export interface SellerProfile {
  id: number;
  user_id: number;
  stores?: Store[];
}

/** User đang đăng nhập. */
export interface AuthUser {
  id: number;
  name: string;
  username: string;
  email: string;
  phone?: string | null;
  avatar_url?: string | null;
  email_verified_at?: string | null;
  /** false với tài khoản tạo qua Google (chưa từng đặt mật khẩu). */
  has_password?: boolean;
  role: "customer" | "seller" | "admin";
  seller_profile?: SellerProfile | null;
  sellerProfile?: SellerProfile | null;
}

/**
 * Địa chỉ giao hàng — hệ CŨ của GHN (tỉnh -> quận/huyện -> phường/xã, CÓ
 * quận/huyện) vì API Tính phí GHN chỉ nhận district_id/ward_code kiểu cũ.
 * *_ghn/district_* là nguồn DUY NHẤT dùng để tính phí/tạo vận đơn — BE tự tra
 * tên thật theo mã (xem services/locations.ts), FE chỉ gửi mã.
 * province_code/ward_code cũ (hệ provinces.open-api.vn) vẫn có thể tồn tại
 * trên địa chỉ tạo trước đây (legacy, không còn được ghi mới), không dùng ở FE nữa.
 */
export interface Address {
  id: number;
  user_id: number;
  recipient_name: string;
  phone: string;
  province_id_ghn: number;
  province_name_ghn: string;
  district_id: number;
  district_name: string;
  ward_code_ghn: string;
  ward_name_ghn: string;
  address_line: string;
  is_default: boolean;
  created_at?: string;
  updated_at?: string;
}

/** Dữ liệu tạo/sửa địa chỉ — chỉ cần gửi mã GHN, BE tự tra tên thật. */
export interface AddressPayload {
  recipient_name: string;
  phone: string;
  province_id: number | null;
  district_id: number | null;
  ward_code: string | null;
  address_line: string;
  is_default?: boolean;
}

/** Đơn đăng ký trở thành người bán. */
export interface SellerApplication {
  id: number;
  user_id: number;
  shop_name: string;
  phone: string | null;
  address: string | null;
  status: "pending" | "approved" | "rejected";
  created_at: string;
  updated_at: string;
}
