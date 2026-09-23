/**
 * Lớp gọi API dùng chung cho toàn bộ client.
 *
 * - Public GET (catalog): không cần cookie.
 * - Auth/nghiệp vụ cần đăng nhập: dùng session Sanctum SPA (cookie), nên trang
 *   phải chạy cùng host với backend. Mặc định backend ở http://localhost:8000,
 *   vì vậy hãy mở client ở http://localhost:5173 (KHÔNG dùng 127.0.0.1) để cookie
 *   session/XSRF đọc được — xem note trong bộ nhớ dự án.
 */
export const API_ORIGIN =
  (import.meta.env.VITE_API_ORIGIN as string | undefined) ??
  "http://localhost:8000";

const BASE_URL =
  (import.meta.env.VITE_API_URL as string | undefined) ?? `${API_ORIGIN}/api`;

type QueryValue = string | number | boolean | undefined | null;

/** Lỗi API có kèm status + payload để nơi gọi xử lý (VD: 422 lấy errors). */
export interface ApiError extends Error {
  status: number;
  payload: unknown;
}

/**
 * Gửi request GET công khai (không cookie) và trả về JSON đã parse.
 */
export async function apiGet<T>(
  path: string,
  params?: Record<string, QueryValue>,
): Promise<T> {
  const url = new URL(`${BASE_URL}${path}`);

  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null) {
        url.searchParams.set(key, String(value));
      }
    }
  }

  const response = await fetch(url.toString(), {
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new Error(`API request failed (${response.status}): ${path}`);
  }

  return response.json() as Promise<T>;
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(
    new RegExp(`(?:^|; )${name}=([^;]*)`),
  );
  return match ? match[1] : null;
}

/** Lấy cookie CSRF nếu chưa có (bắt buộc trước các request thay đổi dữ liệu). */
async function ensureCsrf(): Promise<void> {
  if (readCookie("XSRF-TOKEN")) return;
  await fetch(`${API_ORIGIN}/sanctum/csrf-cookie`, {
    credentials: "include",
  });
}

/**
 * Các path không tự động refresh khi gặp 401 (401 ở đây là có ý nghĩa thật:
 * sai mật khẩu, hết hạn link…). Mọi path khác sẽ thử /refresh một lần rồi retry.
 */
const NO_REFRESH_PATHS = [
  "/login",
  "/register",
  "/forgot-password",
  "/reset-password",
  "/refresh",
  "/logout",
];

/** Gọi /refresh để gia hạn JWT cookie. Trả về true nếu thành công. */
async function tryRefresh(): Promise<boolean> {
  try {
    const res = await fetch(`${BASE_URL}/refresh`, {
      method: "POST",
      headers: { Accept: "application/json" },
      credentials: "include",
    });
    return res.ok;
  } catch {
    return false;
  }
}

/**
 * Request có xác thực bằng JWT trong HttpOnly cookie (credentials: include).
 * Khi gặp 401 trên các route cần đăng nhập, tự gọi /refresh một lần rồi retry
 * → phiên "cuộn" theo refresh_ttl mà không cần đăng nhập lại.
 */
async function authRequest<T>(
  method: string,
  path: string,
  body?: unknown,
  isRetry = false,
): Promise<T> {
  if (method !== "GET") {
    await ensureCsrf();
  }

  const isForm = body instanceof FormData;
  const headers: Record<string, string> = { Accept: "application/json" };
  if (body !== undefined && !isForm) {
    headers["Content-Type"] = "application/json";
  }

  const xsrf = readCookie("XSRF-TOKEN");
  if (xsrf) {
    headers["X-XSRF-TOKEN"] = decodeURIComponent(xsrf);
  }

  const response = await fetch(`${BASE_URL}${path}`, {
    method,
    headers,
    credentials: "include",
    body:
      body === undefined
        ? undefined
        : isForm
          ? (body as FormData)
          : JSON.stringify(body),
  });

  // Tự gia hạn phiên khi token hết hạn.
  if (
    response.status === 401 &&
    !isRetry &&
    !NO_REFRESH_PATHS.includes(path) &&
    (await tryRefresh())
  ) {
    return authRequest<T>(method, path, body, true);
  }

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const error = new Error(
      (data as { message?: string })?.message ?? `API ${response.status}`,
    ) as ApiError;
    error.status = response.status;
    error.payload = data;
    throw error;
  }

  return data as T;
}

export const apiAuthGet = <T>(path: string): Promise<T> =>
  authRequest<T>("GET", path);

export const apiPost = <T>(path: string, body?: unknown): Promise<T> =>
  authRequest<T>("POST", path, body);

export const apiPatch = <T>(path: string, body?: unknown): Promise<T> =>
  authRequest<T>("PATCH", path, body);

export const apiPut = <T>(path: string, body?: unknown): Promise<T> =>
  authRequest<T>("PUT", path, body);

export const apiDelete = <T>(path: string): Promise<T> =>
  authRequest<T>("DELETE", path);

/** Upload multipart (avatar…). Trình duyệt tự set Content-Type + boundary. */
export const apiUpload = <T>(path: string, form: FormData): Promise<T> =>
  authRequest<T>("POST", path, form);
