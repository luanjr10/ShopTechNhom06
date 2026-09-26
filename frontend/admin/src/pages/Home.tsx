import { useAuth } from "../context/AuthContext";
import Dashboard from "./Dashboard";
import SellerDashboard from "./seller/SellerDashboard";

export default function Home() {
  const { user } = useAuth();
  return user?.role === "seller" ? <SellerDashboard /> : <Dashboard />;
}
