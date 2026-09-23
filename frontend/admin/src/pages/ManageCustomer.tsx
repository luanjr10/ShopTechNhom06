import CustomerList from "../components/customer/CustomerList";

function ManageCustomerPage() {
  return (
    <div className="flex flex-col gap-8 px-10 py-10">
      <h2 className="text-2xl text-white font-bold font-sans">
        Quản Lý Khách Hàng
      </h2>
      <CustomerList />
    </div>
  );
}

export default ManageCustomerPage;
