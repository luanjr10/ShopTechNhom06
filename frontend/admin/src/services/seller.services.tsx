import api, { BACKEND_URL } from "../api/axios";

export const getMyStores = async () => {
  const res = await api.get("seller/stores");
  return res.data.data;
};

export const createStore = async (payload: { name: string; description?: string }) => {
  const res = await api.post("seller/stores", payload);
  return res.data;
};

export const updateStore = async (
  storeId: number,
  payload: { name: string; description?: string },
) => {
  const res = await api.patch(`seller/stores/${storeId}`, payload);
  return res.data;
};

export const getStoreProducts = async (
  storeId: number,
  params: { page?: number; sort?: string; search?: string } = {},
) => {
  const res = await api.get(`seller/stores/${storeId}/products`, { params });
  return res.data;
};

export const createStoreProduct = async (storeId: number, form: FormData) => {
  const res = await api.post(`seller/stores/${storeId}/products`, form);
  return res.data;
};

export const updateStoreProduct = async (
  storeId: number,
  productId: number,
  form: FormData,
) => {
  const res = await api.post(`seller/stores/${storeId}/products/${productId}`, form);
  return res.data;
};

export const deleteStoreProduct = async (storeId: number, productId: number) => {
  const res = await api.delete(`seller/stores/${storeId}/products/${productId}`);
  return res.data;
};

export const getStoreOrders = async (storeId: number, status?: string) => {
  const res = await api.get(`seller/stores/${storeId}/orders`, {
    params: { status: status || undefined, per_page: 20 },
  });
  return res.data;
};

export const getStoreOrderDetail = async (storeId: number, orderId: number) => {
  const res = await api.get(`seller/stores/${storeId}/orders/${orderId}`);
  return res.data;
};

export const updateOrderStatus = async (
  storeId: number,
  orderId: number,
  status: "confirmed" | "cancelled",
) => {
  const res = await api.patch(`seller/stores/${storeId}/orders/${orderId}/status`, { status });
  return res.data;
};

export const handoverOrder = async (storeId: number, orderId: number) => {
  const res = await api.post(`seller/stores/${storeId}/orders/${orderId}/handover`);
  return res.data;
};

export const driverMarkDelivered = async (storeId: number, orderId: number) => {
  const res = await api.post(
    `seller/stores/${storeId}/orders/${orderId}/driver-mark-delivered`,
  );
  return res.data;
};

export const driverMarkCancelled = async (storeId: number, orderId: number) => {
  const res = await api.post(
    `seller/stores/${storeId}/orders/${orderId}/driver-mark-cancelled`,
  );
  return res.data;
};

export const updateStorePickupAddress = async (
  storeId: number,
  payload: {
    pickup_contact_name: string;
    pickup_phone: string;
    province_id: number;
    district_id: number;
    ward_code: string;
    address_line: string;
  },
) => {
  const res = await api.put(`seller/stores/${storeId}/pickup-address`, payload);
  return res.data;
};

export const getStoreInventory = async (storeId: number, lowStockOnly = false) => {
  const res = await api.get(`seller/stores/${storeId}/inventory`, {
    params: { low_stock: lowStockOnly || undefined, per_page: 50 },
  });
  return res.data;
};

export const adjustStock = async (
  storeId: number,
  productId: number,
  change: number,
  reason?: string,
) => {
  const res = await api.post(`seller/stores/${storeId}/products/${productId}/stock-adjustments`, {
    change,
    reason,
  });
  return res.data;
};

export const getStoreRevenue = async (storeId: number, days = 30) => {
  const res = await api.get(`seller/stores/${storeId}/revenue`, { params: { days } });
  return res.data.data;
};

export const getWallet = async () => {
  const res = await api.get("seller/wallet");
  return res.data.data;
};

export const getWalletTransactions = async () => {
  const res = await api.get("seller/wallet/transactions");
  return res.data.data;
};

export const getWithdrawals = async () => {
  const res = await api.get("seller/withdrawals");
  return res.data.data;
};

export const createWithdrawal = async (payload: {
  amount: number;
  method: "cod" | "momo" | "vnpay" | "onepay" | "sepay";
  bank_account: string;
  bank_name: string;
  note?: string;
}) => {
  const res = await api.post("seller/withdrawals", payload);
  return res.data;
};

export const emailOrderInvoice = async (storeId: number, orderId: number, email?: string) => {
  const res = await api.post(`seller/stores/${storeId}/orders/${orderId}/invoice/email`, { email });
  return res.data;
};
export const orderInvoicePdfUrl = (storeId: number, orderId: number) =>
  `${BACKEND_URL}/api/seller/stores/${storeId}/orders/${orderId}/invoice/pdf`;

export const getStoreReviews = async (storeId: number, params: { rating?: number; page?: number } = {}) => {
  const res = await api.get(`seller/stores/${storeId}/reviews`, { params });
  return res.data;
};

export const getStoreFollowers = async (storeId: number, page = 1) => {
  const res = await api.get(`seller/stores/${storeId}/followers`, { params: { page, per_page: 15 } });
  return res.data;
};

export const getStoreReturns = async (storeId: number, status?: string, page = 1) => {
  const res = await api.get(`seller/stores/${storeId}/returns`, {
    params: { status: status || undefined, page, per_page: 15 },
  });
  return res.data;
};
export const getStoreReturnDetail = async (storeId: number, returnId: number) => {
  const res = await api.get(`seller/stores/${storeId}/returns/${returnId}`);
  return res.data;
};
export const respondToReturn = async (
  storeId: number,
  returnId: number,
  payload: { status: "approved" | "rejected"; seller_response: string },
) => {
  const res = await api.patch(`seller/stores/${storeId}/returns/${returnId}/respond`, payload);
  return res.data;
};
