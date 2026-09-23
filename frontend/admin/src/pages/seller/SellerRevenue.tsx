import { useEffect, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { getStoreRevenue } from "../../services/seller.services";
import { formatMoneyVietNam } from "../../helpers/formatMoney";
import { RevenueSummary } from "../../types/seller.types";

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="text-xs text-gray-500 dark:text-gray-400">{label}</div>
      <div className="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">{value}</div>
    </div>
  );
}

export default function SellerRevenue() {
  const { activeStore } = useAuth();
  const [days, setDays] = useState(30);
  const [summary, setSummary] = useState<RevenueSummary | null>(null);

  useEffect(() => {
    if (!activeStore) return;
    getStoreRevenue(activeStore.id, days).then(setSummary);
  }, [activeStore, days]);

  if (!activeStore) {
    return (
      <div className="flex flex-col gap-8 px-10 py-10">
        <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Doanh thu</h2>
        <div className="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
          Bạn chưa có gian hàng nào.
        </div>
      </div>
    );
  }

  const maxRevenue = Math.max(1, ...(summary?.series.map((s) => Number(s.revenue)) ?? [0]));

  return (
    <div className="flex flex-col gap-8 px-10 py-10">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-2xl font-bold text-gray-800 dark:text-white">Doanh thu</h2>
        <div className="flex gap-2">
          {[7, 30, 90].map((d) => (
            <button
              key={d}
              onClick={() => setDays(d)}
              className={`rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors ${
                days === d
                  ? "bg-violet-600 text-white"
                  : "bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
              }`}
            >
              {d} ngày
            </button>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Số đơn hoàn tất" value={String(summary?.orders_count ?? 0)} />
        <StatCard label="Tổng giá trị đơn" value={formatMoneyVietNam(summary?.gross_revenue ?? 0)} />
        <StatCard label="Hoa hồng sàn" value={formatMoneyVietNam(summary?.commission_paid ?? 0)} />
        <StatCard label="Doanh thu thực nhận" value={formatMoneyVietNam(summary?.net_revenue ?? 0)} />
      </div>

      <div className="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 className="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">
          Doanh thu theo ngày ({days} ngày gần nhất)
        </h3>
        {!summary || summary.series.length === 0 ? (
          <p className="text-sm text-gray-400">Chưa có đơn hoàn tất trong khoảng thời gian này.</p>
        ) : (
          <div className="flex items-end gap-1 overflow-x-auto pb-2">
            {summary.series.map((point) => (
              <div key={point.date} className="flex min-w-[28px] flex-col items-center gap-1" title={`${point.date}: ${formatMoneyVietNam(point.revenue)}`}>
                <div
                  className="w-4 rounded-t bg-violet-500"
                  style={{ height: `${Math.max(4, (Number(point.revenue) / maxRevenue) * 120)}px` }}
                />
                <span className="rotate-0 text-[10px] text-gray-400">{point.date.slice(5)}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
