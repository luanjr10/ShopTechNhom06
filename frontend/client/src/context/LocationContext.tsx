import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { getProvinces } from "../services/locations";
import { type Province } from "../types/location";

const STORAGE_KEY = "shoptech_selected_province";

interface LocationContextValue {
  provinces: Province[];
  loadingProvinces: boolean;
  /** Tỉnh khách đang chọn ở header — null = xem toàn sàn, không lọc theo tỉnh. */
  selectedProvince: Province | null;
  setSelectedProvince: (province: Province | null) => void;
}

const LocationContext = createContext<LocationContextValue | null>(null);

function readStoredProvince(): Province | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as Province) : null;
  } catch {
    return null;
  }
}

/**
 * Tỉnh/thành khách đang chọn ở header ("Hà Nội" ...) — dùng để lọc gian
 * hàng/sản phẩm theo tỉnh VÀ để tính miễn phí ship + giao nhanh 2 giờ khi
 * trùng tỉnh với kho gian hàng (xem ShippingService::feeForStore backend).
 * Chỉ lưu ở trình duyệt (localStorage) — không đồng bộ server, không cần đăng nhập.
 */
export function LocationProvider({ children }: { children: ReactNode }) {
  const [provinces, setProvinces] = useState<Province[]>([]);
  const [loadingProvinces, setLoadingProvinces] = useState(true);
  const [selectedProvince, setSelectedProvinceState] = useState<Province | null>(
    readStoredProvince,
  );

  useEffect(() => {
    getProvinces()
      .then(setProvinces)
      .catch((error) => console.error("Không tải được danh sách tỉnh/thành:", error))
      .finally(() => setLoadingProvinces(false));
  }, []);

  const setSelectedProvince = useCallback((province: Province | null) => {
    setSelectedProvinceState(province);
    try {
      if (province) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(province));
      } else {
        localStorage.removeItem(STORAGE_KEY);
      }
    } catch {
      // localStorage có thể bị chặn (chế độ ẩn danh) — bỏ qua, state trong phiên vẫn hoạt động.
    }
  }, []);

  return (
    <LocationContext.Provider
      value={{ provinces, loadingProvinces, selectedProvince, setSelectedProvince }}
    >
      {children}
    </LocationContext.Provider>
  );
}

// eslint-disable-next-line react-refresh/only-export-components
export function useLocationContext(): LocationContextValue {
  const ctx = useContext(LocationContext);
  if (!ctx) {
    throw new Error("useLocationContext phải nằm trong <LocationProvider>");
  }
  return ctx;
}
