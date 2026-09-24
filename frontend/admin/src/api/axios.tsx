import axios from "axios";

// Dùng cùng host 'localhost' với frontend để cookie session/XSRF chia sẻ được
// (localhost và 127.0.0.1 là 2 domain khác nhau -> cookie không đọc chéo).
// Production mặc định gọi API cùng domain ("/api/..."), Vercel proxy sang
// backend (xem vercel.json) — không cần biết trước domain Vercel.
export const BACKEND_URL =
  (import.meta.env.VITE_BACKEND_URL as string | undefined) ??
  (import.meta.env.PROD ? "" : "http://localhost:8000");

const api = axios.create({
  baseURL: `${BACKEND_URL}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json" },
});

/** Lấy cookie CSRF trước các request thay đổi dữ liệu (login/POST/PATCH...). */
export async function ensureCsrf(): Promise<void> {
  await axios.get(`${BACKEND_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
  });
}

export default api;
