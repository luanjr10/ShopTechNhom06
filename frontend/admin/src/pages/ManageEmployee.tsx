import EmployeeList from "../components/employee/EmployeeList";

function ManageEmployeePage() {
  return (
    <div className="flex flex-col gap-8 px-10 py-10">
      <h2 className="text-2xl text-white font-bold font-sans">
        Quản Lý Nhân Viên
      </h2>
      <EmployeeList />
    </div>
  );
}

export default ManageEmployeePage;
