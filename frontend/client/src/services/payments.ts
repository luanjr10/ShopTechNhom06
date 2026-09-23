import { apiPost } from "../libs/api";

interface PaymentUrlResponse {
  success: boolean;
  data: { pay_url: string };
}

/** Tạo yêu cầu thanh toán cho 1 order đã đặt (payment_method=momo) và trả về payUrl để redirect. */
export const createMomoPayment = async (orderId: number): Promise<string> =>
  (await apiPost<PaymentUrlResponse>("/payments/momo/create", { order_id: orderId })).data.pay_url;

export const createVnpayPayment = async (orderId: number): Promise<string> =>
  (await apiPost<PaymentUrlResponse>("/payments/vnpay/create", { order_id: orderId })).data.pay_url;

/**
 * Tạo yêu cầu thanh toán OnePay. `flow` chọn luồng:
 *   - "domestic" — chỉ thẻ ATM nội địa Napas (trang OnePay rút gọn)
 *   - "international" — trang OnePay đầy đủ phương thức: Visa/Master/JCB,
 *     Apple/Google/Samsung Pay, ATM nội địa, Mobile Banking/VietQR... (mặc định)
 */
export const createOnepayPayment = async (
  orderId: number,
  flow: "domestic" | "international" = "international",
): Promise<string> =>
  (await apiPost<PaymentUrlResponse>("/payments/onepay/create", { order_id: orderId, flow })).data.pay_url;

/** Tạo QR checkout SePay để khách quét/chuyển khoản. */
export const createSepayPayment = async (orderId: number): Promise<string> =>
  (await apiPost<PaymentUrlResponse>("/payments/sepay/create", { order_id: orderId })).data.pay_url;
