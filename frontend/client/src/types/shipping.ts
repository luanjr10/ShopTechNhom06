/** Phí ship theo từng gian hàng trong giỏ (mỗi store có kho/tuyến khác nhau). */
export interface ShippingFeeByStore {
  store_id: number;
  store_name: string;
  fee: number;
  weight: number;
  length: number;
  width: number;
  height: number;
  /** true nếu địa chỉ nhận cùng tỉnh với kho gian hàng — miễn phí ship + giao nhanh (2 giờ), không qua GHN. */
  same_province_express: boolean;
}

/** Kết quả tính phí GHN thật cho toàn bộ giỏ hàng — KHÔNG có free-shipping mặc định (trừ đơn cùng tỉnh với gian hàng). */
export interface ShippingQuote {
  total_fee: number;
  expected_delivery_time: string | null;
  by_store: ShippingFeeByStore[];
}
