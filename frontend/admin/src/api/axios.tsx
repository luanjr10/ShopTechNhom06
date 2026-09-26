import axios from "axios";

export const BACKEND_URL =
  (import.meta.env.VITE_BACKEND_URL as string | undefined) ??
  (import.meta.env.PROD ? "" : "http://localhost:8000");

const api = axios.create({
  baseURL: `${BACKEND_URL}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json" },
});

export async function ensureCsrf(): Promise<void> {
  await axios.get(`${BACKEND_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
  });
}

export default api;
