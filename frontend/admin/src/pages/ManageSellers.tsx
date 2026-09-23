import SellerApplicationList from "../components/seller/SellerApplicationList";

function ManageSellersPage() {
  return (
    <div className="flex flex-col gap-8 px-10 py-10">
      <h2 className="text-2xl text-white font-bold font-sans">
        Quản Lý Người Bán
      </h2>
      <SellerApplicationList />
    </div>
  );
}

export default ManageSellersPage;
