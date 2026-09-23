/**
 * Địa chỉ hành chính Việt Nam — hệ CŨ của GHN (tỉnh -> quận/huyện -> phường/xã,
 * CÓ quận/huyện). Đổi từ provinces.open-api.vn (2 cấp, không quận/huyện) sang
 * đây vì API Tính phí GHN chỉ nhận district_id + ward_code kiểu cũ.
 */
export interface Province {
  code: number;
  name: string;
}

export interface District {
  code: number;
  name: string;
}

/** Mã phường/xã GHN là chuỗi (vd "1A0605"), không phải số. */
export interface Ward {
  code: string;
  name: string;
}
