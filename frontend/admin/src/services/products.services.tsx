import api from "../api/axios";
import { ProductSort } from "../types/products.types";

export const getAllProducts = async (params?: {
  page?: number;
  sort?: ProductSort;
  search?: string;
}) => {
  try {
    const response = await api.get("products", { params });
    return response.data;
  } catch (error) {
    console.log(error);
  }
};

export const getProductById = async (id: number) => {
  try {
    const response = await api.get(`products/${id}`);
    return response.data;
  } catch (error) {
    console.log(error);
  }
};

export const createProduct = async (data: FormData) => {
  try {
    const response = await api.post("/products", data);
    return response.data;
  } catch (error: any) {
    console.log("STATUS:", error.response?.status);
    console.log("DATA FROM LARAVEL:", error.response?.data);
    console.log("ERRORS:", error.response?.data?.errors);

    throw error;
  }
};

export const updateProduct = async (id: number, data: FormData) => {
  try {
    // Laravel spoof method: gửi POST kèm _method=PUT để hỗ trợ upload file multipart
    data.append("_method", "PUT");
    const response = await api.post(`/products/${id}`, data);
    return response.data;
  } catch (error: any) {
    console.log("STATUS:", error.response?.status);
    console.log("DATA FROM LARAVEL:", error.response?.data);
    console.log("ERRORS:", error.response?.data?.errors);

    throw error;
  }
};

export const deleteProduct = async (id: number) => {
  try {
    const response = await api.delete(`/products/${id}`);
    return response.data;
  } catch (error) {
    console.log(error);
    throw error;
  }
};