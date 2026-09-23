import { useState } from "react";
import ProductsList from "../components/products/ProductList";
import BrandList from "../components/brands/BrandList";

function ManageBrandsPage() {
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [loading, setLoading] = useState(false);
  return (
    <>
      <div className="flex flex-col gap-8 px-10 py-10">
        <h2 className="text-2xl text-white font-bold font-sans">
          Quản Lý Thương Hiệu
        </h2>
        <BrandList />
      </div>
    </>
  );
}

export default ManageBrandsPage;
