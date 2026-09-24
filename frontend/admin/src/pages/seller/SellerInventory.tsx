import { useCallback, useEffect, useState } from "react";
import { ToastContainer } from "react-toastify";
import { useAuth } from "../../context/AuthContext";
import { getStoreInventory } from "../../services/seller.services";
import DataTable, { Column } from "../../components/common/DataTable";
import StockAdjustModal from "../../components/seller-center/StockAdjustModal";
import { InventoryItem } from "../../types/seller.types";

export default function SellerInventory() {
  const { activeStore } = useAuth();
  const [items, setItems] = useState<InventoryItem[]>([]);
  const [lowStockOnly, setLowStockOnly] = useState(false);
  const [adjusting, setAdjusting] = useState<InventoryItem | null>(null);

  const load = useCallback(() => {
    if (!activeStore) return;
    getStoreInventory(activeStore.id, lowStockOnly).then((res) => setItems(res.data ?? []));
  }, [activeStore, lowStockOnly]);

  useEffect(load, [load]);

  if (!activeStore) {
    return (
      <div className="flex flex-col gap-6 px-4 py-6 sm:gap-8 sm:px-6 sm:py-8 lg:px-10 lg:py-10">
        <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Kho hàng</h2>
        <div className="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
          Bạn chưa có gian hàng nào.
        </div>
      </div>
    );
  }

  const columns: Column<InventoryItem>[] = [
    { header: "Mã", render: (p) => <span className="font-mono text-gray-500 dark:text-gray-400">{p.code}</span> },
    { header: "Sản phẩm", render: (p) => <span className="font-medium text-gray-800 dark:text-white">{p.name}</span> },
    {
      header: "Tồn kho",
      render: (p) => (
        <span className={p.low_stock ? "font-semibold text-rose-500" : "text-gray-700 dark:text-gray-300"}>
          {p.stock}
        </span>
      ),
    },
    {
      header: "Cảnh báo",
      render: (p) =>
        p.low_stock ? (
          <span className="inline-flex rounded-full border border-rose-500/20 bg-rose-500/10 px-2.5 py-0.5 text-xs font-medium text-rose-500">
            Sắp hết hàng
          </span>
        ) : (
          <span className="text-xs text-gray-400">—</span>
        ),
    },
    {
      header: "Thao tác",
      align: "center",
      render: (p) => (
        <div className="flex justify-center">
          <button
            onClick={() => setAdjusting(p)}
            className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-500"
          >
            Điều chỉnh tồn kho
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6 px-4 py-6 sm:gap-8 sm:px-6 sm:py-8 lg:px-10 lg:py-10">
      <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Kho hàng</h2>

      <DataTable
        title="Tồn kho"
        subtitle={activeStore.name}
        data={items}
        columns={columns}
        rowKey={(p) => p.id}
        totalItems={items.length}
        filters={
          <button
            onClick={() => setLowStockOnly((v) => !v)}
            className={`rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors ${
              lowStockOnly
                ? "bg-rose-600 text-white"
                : "bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            }`}
          >
            Chỉ hiện sắp hết hàng
          </button>
        }
      />

      <StockAdjustModal
        open={!!adjusting}
        storeId={activeStore.id}
        item={adjusting}
        onClose={() => setAdjusting(null)}
        onSaved={() => {
          setAdjusting(null);
          load();
        }}
      />

      <ToastContainer />
    </div>
  );
}
