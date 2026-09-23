import { useEffect, useState } from "react";
import { Label, Select, Textarea, TextInput } from "flowbite-react";
import { useAuth } from "../../context/AuthContext";
import { updateStore, updateStorePickupAddress } from "../../services/seller.services";
import { getDistricts, getProvinces, getWards, type LocationOption } from "../../services/locations.services";
import { notifyError, notifySuccess } from "../../helpers/notify";

export default function SellerSettings() {
  const { activeStore, refreshStores } = useAuth();
  const [submitting, setSubmitting] = useState(false);

  // ---- Địa chỉ lấy hàng (GHN) ----
  const [provinces, setProvinces] = useState<LocationOption[]>([]);
  const [districts, setDistricts] = useState<LocationOption[]>([]);
  const [wards, setWards] = useState<LocationOption[]>([]);
  const [provinceId, setProvinceId] = useState<string>("");
  const [districtId, setDistrictId] = useState<string>("");
  const [wardCode, setWardCode] = useState<string>("");
  const [pickupContactName, setPickupContactName] = useState("");
  const [pickupPhone, setPickupPhone] = useState("");
  const [addressLine, setAddressLine] = useState("");
  const [savingAddress, setSavingAddress] = useState(false);

  useEffect(() => {
    getProvinces().then(setProvinces);
  }, []);

  // Điền sẵn địa chỉ hiện có của store (nếu có) khi đổi store hoặc danh sách tỉnh vừa tải xong.
  useEffect(() => {
    if (!activeStore) return;
    setPickupContactName(activeStore.pickup_contact_name ?? "");
    setPickupPhone(activeStore.pickup_phone ?? "");
    setAddressLine(activeStore.address_line ?? "");
    setProvinceId(activeStore.province_id ? String(activeStore.province_id) : "");
    setDistrictId(activeStore.district_id ? String(activeStore.district_id) : "");
    setWardCode(activeStore.ward_code ?? "");
  }, [activeStore]);

  useEffect(() => {
    if (!provinceId) {
      setDistricts([]);
      return;
    }
    getDistricts(Number(provinceId)).then(setDistricts);
  }, [provinceId]);

  useEffect(() => {
    if (!districtId) {
      setWards([]);
      return;
    }
    getWards(Number(districtId)).then(setWards);
  }, [districtId]);

  if (!activeStore) {
    return (
      <div className="flex flex-col gap-8 px-10 py-10">
        <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Cài đặt</h2>
        <div className="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
          Bạn chưa có gian hàng nào để chỉnh sửa.
        </div>
      </div>
    );
  }

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setSubmitting(true);
    const form = new FormData(e.currentTarget);
    try {
      await updateStore(activeStore.id, {
        name: String(form.get("name")),
        description: String(form.get("description") || ""),
      });
      notifySuccess("Cập nhật gian hàng thành công");
      refreshStores();
    } catch (err: any) {
      notifyError(err?.response?.data?.message ?? "Cập nhật thất bại");
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveAddress = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!provinceId || !districtId || !wardCode) {
      notifyError("Vui lòng chọn đủ Tỉnh/Thành phố, Quận/Huyện, Phường/Xã");
      return;
    }
    setSavingAddress(true);
    try {
      await updateStorePickupAddress(activeStore.id, {
        pickup_contact_name: pickupContactName,
        pickup_phone: pickupPhone,
        province_id: Number(provinceId),
        district_id: Number(districtId),
        ward_code: wardCode,
        address_line: addressLine,
      });
      notifySuccess("Đã cập nhật địa chỉ lấy hàng");
      refreshStores();
    } catch (err: any) {
      notifyError(err?.response?.data?.message ?? "Cập nhật địa chỉ lấy hàng thất bại");
    } finally {
      setSavingAddress(false);
    }
  };

  return (
    <div className="flex flex-col gap-8 px-10 py-10">
      <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Cài đặt gian hàng</h2>

      <form
        onSubmit={handleSubmit}
        className="max-w-lg space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
      >
        <div>
          <Label htmlFor="name">Tên gian hàng</Label>
          <TextInput id="name" name="name" required defaultValue={activeStore.name} />
        </div>
        <div>
          <Label htmlFor="description">Mô tả</Label>
          <Textarea id="description" name="description" rows={4} defaultValue={activeStore.description ?? ""} />
        </div>
        <p className="text-xs text-gray-400">
          Trạng thái duyệt gian hàng chỉ do admin thay đổi, không thể tự chỉnh ở đây.
        </p>
        <button
          type="submit"
          disabled={submitting}
          className="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-60"
        >
          {submitting ? "Đang lưu..." : "Lưu thay đổi"}
        </button>
      </form>

      <form
        onSubmit={handleSaveAddress}
        className="max-w-lg space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
      >
        <div>
          <h3 className="text-base font-semibold text-gray-800 dark:text-white">Địa chỉ lấy hàng (GHN)</h3>
          <p className="text-xs text-gray-400">
            Bắt buộc trước khi có thể "Bàn giao vận chuyển" — Giao Hàng Nhanh sẽ đến lấy hàng tại đây.
          </p>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <Label htmlFor="pickup_contact_name">Tên liên hệ</Label>
            <TextInput
              id="pickup_contact_name"
              required
              value={pickupContactName}
              onChange={(e) => setPickupContactName(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="pickup_phone">Số điện thoại</Label>
            <TextInput
              id="pickup_phone"
              required
              value={pickupPhone}
              onChange={(e) => setPickupPhone(e.target.value)}
            />
          </div>
        </div>

        <div className="grid grid-cols-3 gap-3">
          <div>
            <Label htmlFor="province">Tỉnh/Thành phố</Label>
            <Select
              id="province"
              required
              value={provinceId}
              onChange={(e) => {
                setProvinceId(e.target.value);
                setDistrictId("");
                setWardCode("");
              }}
            >
              <option value="">-- Chọn --</option>
              {provinces.map((p) => (
                <option key={p.code} value={p.code}>
                  {p.name}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="district">Quận/Huyện</Label>
            <Select
              id="district"
              required
              disabled={!provinceId}
              value={districtId}
              onChange={(e) => {
                setDistrictId(e.target.value);
                setWardCode("");
              }}
            >
              <option value="">-- Chọn --</option>
              {districts.map((d) => (
                <option key={d.code} value={d.code}>
                  {d.name}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <Label htmlFor="ward">Phường/Xã</Label>
            <Select
              id="ward"
              required
              disabled={!districtId}
              value={wardCode}
              onChange={(e) => setWardCode(e.target.value)}
            >
              <option value="">-- Chọn --</option>
              {wards.map((w) => (
                <option key={w.code} value={w.code}>
                  {w.name}
                </option>
              ))}
            </Select>
          </div>
        </div>

        <div>
          <Label htmlFor="address_line">Địa chỉ cụ thể</Label>
          <TextInput
            id="address_line"
            required
            placeholder="Số nhà, đường..."
            value={addressLine}
            onChange={(e) => setAddressLine(e.target.value)}
          />
        </div>

        <button
          type="submit"
          disabled={savingAddress}
          className="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-60"
        >
          {savingAddress ? "Đang lưu..." : "Lưu địa chỉ lấy hàng"}
        </button>
      </form>
    </div>
  );
}
