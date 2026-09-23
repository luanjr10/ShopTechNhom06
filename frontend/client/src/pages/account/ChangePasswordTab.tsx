import { useState } from "react";
import { Loader2, LockKeyhole } from "lucide-react";
import { changePassword } from "../../services/account";
import { type ApiError } from "../../libs/api";

type FieldErrors = Record<string, string[]>;

const initial = {
  current_password: "",
  password: "",
  password_confirmation: "",
};

function ChangePasswordTab() {
  const [form, setForm] = useState(initial);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setMessage(null);
    setSaving(true);
    try {
      const msg = await changePassword(form);
      setMessage(msg);
      setForm(initial);
    } catch (err) {
      const apiErr = err as ApiError;
      const payload = apiErr?.payload as { errors?: FieldErrors } | undefined;
      if (payload?.errors) setErrors(payload.errors);
      else setMessage(apiErr?.message ?? "Đổi mật khẩu thất bại");
    } finally {
      setSaving(false);
    }
  };

  const fields = [
    { name: "current_password", label: "Mật khẩu hiện tại" },
    { name: "password", label: "Mật khẩu mới" },
    { name: "password_confirmation", label: "Xác nhận mật khẩu mới" },
  ] as const;

  return (
    <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
      <h2 className="mb-1 font-sans text-[18px] font-bold text-gray-800">
        Đổi mật khẩu
      </h2>
      <p className="mb-5 font-sans text-[13px] text-gray-500">
        Sau khi đổi, các thiết bị khác sẽ bị đăng xuất để đảm bảo an toàn
      </p>

      {message && (
        <div className="mb-4 rounded-lg bg-green-50 px-3 py-2 font-sans text-[13px] text-green-700">
          {message}
        </div>
      )}

      <form onSubmit={handleSubmit} className="flex max-w-md flex-col gap-4">
        {fields.map((f) => (
          <div key={f.name}>
            <label className="mb-1 block font-sans text-[13px] font-medium text-gray-700">
              {f.label}
            </label>
            <div className="relative">
              <LockKeyhole className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400" />
              <input
                type="password"
                value={form[f.name]}
                onChange={(e) => setForm({ ...form, [f.name]: e.target.value })}
                placeholder={f.label}
                className={`w-full rounded-lg border py-2.5 pr-3 pl-9 font-sans text-[14px] outline-none focus:border-primary500 ${
                  errors[f.name] ? "border-primary500" : "border-gray-200"
                }`}
              />
            </div>
            {errors[f.name] && (
              <p className="mt-1 font-sans text-[12px] text-primary500">
                {errors[f.name][0]}
              </p>
            )}
          </div>
        ))}
        <button
          type="submit"
          disabled={saving}
          className="mt-1 flex items-center justify-center gap-2 rounded-lg bg-primary500 py-2.5 font-sans text-[14px] font-semibold text-white transition-colors hover:bg-primary300 disabled:opacity-60 cursor-pointer sm:self-start sm:px-8"
        >
          {saving && <Loader2 className="size-4 animate-spin" />}
          Đổi mật khẩu
        </button>
      </form>
    </div>
  );
}

export default ChangePasswordTab;
