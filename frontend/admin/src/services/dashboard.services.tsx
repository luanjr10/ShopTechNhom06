import api from "../api/axios";
import { DashboardSummary, SellerDashboardSummary } from "../types/dashboard.types";

/** Tổng quan toàn sàn cho trang Dashboard admin — toàn bộ số liệu tính thật từ DB. */
export const getDashboardSummary = async (): Promise<DashboardSummary> => {
  const res = await api.get("admin/dashboard");
  return res.data.data;
};

/** Tổng quan MỘT gian hàng cho trang Dashboard seller — toàn bộ số liệu tính thật từ DB. */
export const getSellerDashboardSummary = async (storeId: number): Promise<SellerDashboardSummary> => {
  const res = await api.get(`seller/stores/${storeId}/dashboard`);
  return res.data.data;
};
