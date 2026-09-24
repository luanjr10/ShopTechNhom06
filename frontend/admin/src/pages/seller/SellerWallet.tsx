import { useEffect, useState } from "react";
import { ToastContainer } from "react-toastify";
import { getWallet, getWalletTransactions } from "../../services/seller.services";
import { formatMoneyVietNam } from "../../helpers/formatMoney";
import { formatDate } from "../../helpers/formatDate";
import DataTable, { Column } from "../../components/common/DataTable";
import { WalletInfo, WalletTransaction } from "../../types/seller.types";

const TX_LABEL: Record<string, string> = {
  hold: "Giữ chỗ khi có đơn",
  release: "Ghi nhận khi đơn hoàn tất",
  reserve_withdrawal: "Giữ chỗ để rút tiền",
  payout: "Đã thanh toán rút tiền",
  refund_withdrawal: "Hoàn lại (rút tiền bị từ chối)",
};

export default function SellerWallet() {
  const [wallet, setWallet] = useState<WalletInfo | null>(null);
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);

  useEffect(() => {
    getWallet().then(setWallet);
    getWalletTransactions().then((res) => setTransactions(res.data ?? []));
  }, []);

  const columns: Column<WalletTransaction>[] = [
    { header: "Loại", render: (t) => TX_LABEL[t.type] ?? t.type },
    {
      header: "Số tiền",
      render: (t) => {
        const amount = Number(t.amount);
        return (
          <span className={amount >= 0 ? "text-emerald-500" : "text-rose-500"}>
            {amount >= 0 ? "+" : ""}
            {formatMoneyVietNam(amount)}
          </span>
        );
      },
    },
    { header: "Ghi chú", render: (t) => t.note ?? "—" },
    { header: "Thời gian", render: (t) => formatDate(t.created_at) },
  ];

  return (
    <div className="flex flex-col gap-6 px-4 py-6 sm:gap-8 sm:px-6 sm:py-8 lg:px-10 lg:py-10">
      <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Ví người bán</h2>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="text-xs text-gray-500 dark:text-gray-400">Tổng số dư</div>
          <div className="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">
            {formatMoneyVietNam(wallet?.balance ?? 0)}
          </div>
        </div>
        <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="text-xs text-gray-500 dark:text-gray-400">Đang chờ (chưa hoàn tất đơn)</div>
          <div className="mt-1 text-xl font-semibold text-amber-500">
            {formatMoneyVietNam(wallet?.pending_balance ?? 0)}
          </div>
        </div>
        <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
          <div className="text-xs text-gray-500 dark:text-gray-400">Có thể rút</div>
          <div className="mt-1 text-xl font-semibold text-emerald-500">
            {formatMoneyVietNam(wallet?.withdrawable_balance ?? 0)}
          </div>
        </div>
      </div>

      <DataTable
        title="Lịch sử giao dịch"
        data={transactions}
        columns={columns}
        rowKey={(t) => t.id}
        totalItems={transactions.length}
      />

      <ToastContainer />
    </div>
  );
}
