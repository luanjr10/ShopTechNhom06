import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

export default function LoginPage() {
  const { login, logout } = useAuth();
  const navigate = useNavigate();
  const [loginId, setLoginId] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const user = await login(loginId, password);
      if (user.role !== "admin" && user.role !== "seller" && user.role !== "employee") {
        await logout();
        setError("Tài khoản này không có quyền truy cập trang quản lý");
        return;
      }
      navigate("/");
    } catch (err: any) {
      setError(err?.response?.data?.message ?? "Đăng nhập thất bại");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-950 px-4">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-sm rounded-2xl border border-slate-800 bg-slate-900 p-8"
      >
        <h1 className="mb-1 text-center text-xl font-bold text-white">
          ShopTech Dashboard
        </h1>
        <p className="mb-6 text-center text-sm text-slate-400">
          Đăng nhập quản trị viên / người bán
        </p>

        {error && (
          <div className="mb-4 rounded-lg bg-rose-500/10 px-3 py-2 text-sm text-rose-400">
            {error}
          </div>
        )}

        <label className="mb-1 block text-sm text-slate-300">
          Tên đăng nhập hoặc email
        </label>
        <input
          value={loginId}
          onChange={(e) => setLoginId(e.target.value)}
          required
          className="mb-4 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500"
          placeholder="admin"
        />
        <label className="mb-1 block text-sm text-slate-300">Mật khẩu</label>
        <input
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
          className="mb-6 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500"
          placeholder="••••••••"
        />
        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
        >
          {submitting ? "Đang đăng nhập..." : "Đăng nhập"}
        </button>
      </form>
    </div>
  );
}
