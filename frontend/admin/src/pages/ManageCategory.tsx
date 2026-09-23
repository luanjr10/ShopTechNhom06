import { useState } from "react";
import CategoriesList from "../components/category/CategoryList";

function ManageCategoriesPage() {
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [loading, setLoading] = useState(false);
  return (
    <>
      <div className="flex flex-col gap-8 px-10 py-10">
        <h2 className="text-2xl text-white font-bold font-sans">
          Quản Lý Danh Mục Sản Phẩm
        </h2>
        <CategoriesList />
      </div>
    </>
  );
}

export default ManageCategoriesPage;
