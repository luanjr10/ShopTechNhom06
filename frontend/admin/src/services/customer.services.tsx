import api from "../api/axios";

/**
 * Quản lý khách hàng — CHỈ XEM (không có sửa/xoá, khách tự quản lý hồ sơ của
 * họ ở client). Admin xem toàn sàn, seller chỉ xem khách đã mua tại gian hàng
 * của mình — xem routes/api/admin.php + routes/api/seller.php.
 */

export interface CustomerRow {
  id: number;
  name: string;
  username: string;
  email: string;
  phone?: string | null;
  avatar_url?: string | null;
  created_at?: string;
  total_spent: number;
  orders_count: number;
  tier: string;
  tier_label: string;
}

export interface CustomerListParams {
  search?: string;
  tier?: string;
  page?: number;
  per_page?: number;
}

// ---- Admin: khách hàng toàn sàn ----
export const getCustomers = async (params: CustomerListParams = {}) => {
  const res = await api.get("admin/customers", { params });
  return res.data;
};

export const getCustomerDetail = async (id: number) => {
  const res = await api.get(`admin/customers/${id}`);
  return res.data;
};

// ---- Seller: khách hàng của 1 gian hàng ----
export const getStoreCustomers = async (
  storeId: number,
  params: CustomerListParams = {},
) => {
  const res = await api.get(`seller/stores/${storeId}/customers`, { params });
  return res.data;
};

export const getStoreCustomerDetail = async (storeId: number, customerId: number) => {
  const res = await api.get(`seller/stores/${storeId}/customers/${customerId}`);
  return res.data;
};
