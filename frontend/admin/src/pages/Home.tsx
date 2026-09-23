import { useAuth } from "../context/AuthContext";
import Dashboard from "./Dashboard";
import SellerDashboard from "./seller/SellerDashboard";

/** "/" hiển thị dashboard khác nhau theo role — cùng 1 route, không lộ mục admin cho seller. */
export default function Home() {
  const { user } = useAuth();
  return user?.role === "seller" ? <SellerDashboard /> : <Dashboard />;
}
