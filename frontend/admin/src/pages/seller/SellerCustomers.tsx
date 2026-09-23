import { useAuth } from "../../context/AuthContext";
import SellerCustomerList from "../../components/customer/SellerCustomerList";

export default function SellerCustomers() {
  const { activeStore } = useAuth();

  if (!activeStore) {
    return (
      <div className="flex flex-col gap-8 px-10 py-10">
        <h2 className="font-sans text-2xl font-bold text-white">Khách hàng</h2>
        <p className="text-sm text-gray-400">Bạn cần tạo/chọn 1 gian hàng trước.</p>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-8 px-10 py-10">
      <h2 className="font-sans text-2xl font-bold text-white">Khách hàng</h2>
      <SellerCustomerList />
    </div>
  );
}
