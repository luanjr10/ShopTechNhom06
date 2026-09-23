import { apiPost } from "../libs/api";

export interface CouponResult {
  code: string;
  discount_amount: number;
  discount_target: "subtotal" | "shipping";
  is_free_ship: boolean;
}

interface ApplyCouponResponse {
  success: boolean;
  data: CouponResult;
}

/** Xem trước số tiền giảm — backend luôn tính lại lúc đặt hàng, đây chỉ để hiển thị. */
export const applyCoupon = async (
  code: string,
  subtotal: number,
  shippingFee = 0,
): Promise<CouponResult> =>
  (await apiPost<ApplyCouponResponse>("/coupons/apply", { code, subtotal, shipping_fee: shippingFee })).data;
