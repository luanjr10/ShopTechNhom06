import { apiAuthGet, apiPost } from "../libs/api";
import { type SellerApplication } from "../types/auth";

export interface SellerApplicationPayload {
  shop_name: string;
  phone?: string;
  address?: string;
  category_ids: number[];
}

/** Gửi đơn đăng ký trở thành người bán. */
export async function submitSellerApplication(
  payload: SellerApplicationPayload,
): Promise<SellerApplication> {
  const res = await apiPost<{ success: boolean; data: SellerApplication }>(
    "/seller-applications",
    payload,
  );
  return res.data;
}

/** Danh sách đơn đăng ký của tôi (mới nhất trước). */
export async function getMySellerApplications(): Promise<SellerApplication[]> {
  const res = await apiAuthGet<{ success: boolean; data: SellerApplication[] }>(
    "/seller-applications/mine",
  );
  return res.data ?? [];
}
