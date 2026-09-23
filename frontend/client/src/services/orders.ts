import { apiAuthGet, apiPost } from "../libs/api";
import { type Order, type OrderListResponse, type PaymentMethod } from "../types/order";

export interface PlaceOrderPayload {
  items: Array<{ product_id: number; sku: string | null; quantity: number }>;
  receiver_name: string;
  receiver_phone: string;
  shipping_address: string;
  // Mã GHN (hệ cũ, có quận/huyện) — bắt buộc để BE tính phí/tạo vận đơn thật.
  province_id: number;
  province_name: string;
  district_id: number;
  district_name: string;
  ward_code: string;
  ward_name: string;
  payment_method: PaymentMethod;
  coupon_code?: string | null;
}

interface PlaceOrderResponse {
  success: boolean;
  message: string;
  data: { id: number; total_amount: number };
}

interface OrderDetailResponse {
  success: boolean;
  data: Order;
}

/** Đặt hàng từ giỏ hàng — giá/tồn kho/phí ship luôn được backend tính lại, không tin dữ liệu FE gửi lên. */
export const placeOrder = async (
  payload: PlaceOrderPayload,
): Promise<PlaceOrderResponse["data"]> =>
  (await apiPost<PlaceOrderResponse>("/orders", payload)).data;

/** Danh sách đơn hàng của tôi (mới nhất trước). */
export const getMyOrders = async (page = 1): Promise<OrderListResponse["data"]> =>
  (await apiAuthGet<OrderListResponse>(`/orders/mine?page=${page}`)).data;

/** Chi tiết 1 đơn hàng (kèm trạng thái từng gian hàng). */
export const getOrder = async (id: number): Promise<Order> =>
  (await apiAuthGet<OrderDetailResponse>(`/orders/${id}`)).data;

/** Hủy đơn — BE tự chặn nếu đã có gian hàng xác nhận/đang giao. */
export const cancelOrder = async (id: number): Promise<Order> =>
  (await apiPost<OrderDetailResponse>(`/orders/${id}/cancel`)).data;

/**
 * Khách xác nhận ĐÃ NHẬN hàng cho 1 gian hàng trong đơn — chỉ khi đang
 * 'delivered'. Response BE trả về SellerOrder (không phải Order đầy đủ với
 * seller_orders lồng nhau) nên nơi gọi tự getOrder() lại để cập nhật UI.
 */
export const completeSellerOrder = async (
  orderId: number,
  sellerOrderId: number,
): Promise<void> => {
  await apiPost(`/orders/${orderId}/seller-orders/${sellerOrderId}/complete`);
};
