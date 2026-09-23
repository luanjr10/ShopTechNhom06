import { apiAuthGet, apiPost } from "../libs/api";
import type { LoyaltySummary, MyVoucher } from "../types/loyalty";

interface LoyaltyResponse {
  success: boolean;
  data: LoyaltySummary;
}

interface VouchersResponse {
  success: boolean;
  data: MyVoucher[];
}

interface ClaimResponse {
  success: boolean;
  message: string;
}

/** Hạng thành viên của khách đang đăng nhập. */
export const getLoyaltySummary = async (): Promise<LoyaltySummary> =>
  (await apiAuthGet<LoyaltyResponse>("/loyalty/summary")).data;

/** Voucher hạng thành viên khách ĐỦ ĐIỀU KIỆN nhận (theo hạng hiện tại). */
export const getMyVouchers = async (): Promise<MyVoucher[]> =>
  (await apiAuthGet<VouchersResponse>("/vouchers/mine")).data;

/** Bấm "Nhận" voucher — sau đó mới áp dụng được mã ở checkout. */
export const claimVoucher = async (couponId: number): Promise<ClaimResponse> =>
  apiPost<ClaimResponse>(`/vouchers/${couponId}/claim`);
