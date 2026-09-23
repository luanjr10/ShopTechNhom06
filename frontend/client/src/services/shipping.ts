import { apiPost } from "../libs/api";
import { type ShippingQuote } from "../types/shipping";

export interface ShippingFeeItem {
  product_id: number;
  quantity: number;
}

/**
 * Tính phí vận chuyển THẬT qua GHN cho giỏ hàng — luôn hỏi backend (BE gọi GHN
 * thật, tự lấy weight/dimension từ Product). KHÔNG có fallback 0đ: lỗi (thiếu
 * khối lượng, gian hàng chưa có địa chỉ lấy hàng, GHN lỗi...) sẽ throw, nơi gọi
 * PHẢI chặn thanh toán khi gặp lỗi này, không tự coi là miễn phí ship.
 */
export const calculateShippingFee = async (
  districtId: number,
  wardCode: string,
  items: ShippingFeeItem[],
  provinceId?: number,
): Promise<ShippingQuote> =>
  (
    await apiPost<{ data: ShippingQuote }>("/shipping/fee", {
      district_id: districtId,
      ward_code: wardCode,
      province_id: provinceId,
      items,
    })
  ).data;
