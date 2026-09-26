import { apiPost } from "../libs/api";

interface PaymentUrlResponse {
  success: boolean;
  data: { pay_url: string };
}

export const createMomoPayment = async (orderId: number): Promise<string> =>
  (await apiPost<PaymentUrlResponse>("/payments/momo/create", { order_id: orderId })).data.pay_url;

export const createVnpayPayment = async (orderId: number): Promise<string> =>
  (await apiPost<PaymentUrlResponse>("/payments/vnpay/create", { order_id: orderId })).data.pay_url;

export const createOnepayPayment = async (
  orderId: number,
  flow: "domestic" | "international" = "international",
): Promise<string> =>
  (await apiPost<PaymentUrlResponse>("/payments/onepay/create", { order_id: orderId, flow })).data.pay_url;

export const createSepayPayment = async (orderId: number): Promise<string> =>
  (await apiPost<PaymentUrlResponse>("/payments/sepay/create", { order_id: orderId })).data.pay_url;
