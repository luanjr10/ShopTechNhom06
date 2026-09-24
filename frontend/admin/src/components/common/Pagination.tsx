import { ArrowLeft, ArrowRight } from "lucide-react";

/** Dãy số trang rút gọn: 1 … 4 5 6 … 20 (null = dấu …) để không tràn màn hình nhỏ. */
function pageWindow(current: number, total: number): (number | null)[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const pages: (number | null)[] = [1];
  const start = Math.max(2, current - 1);
  const end = Math.min(total - 1, current + 1);
  if (start > 2) pages.push(null);
  for (let p = start; p <= end; p++) pages.push(p);
  if (end < total - 1) pages.push(null);
  pages.push(total);
  return pages;
}

interface PaginationProps {
  currentPage: number;
  totalPages: number;
  totalItems: number;
  itemsOnPage: number;
  onPageChange: (page: number) => void;
}

export default function Pagination({
  currentPage,
  totalPages,
  totalItems,
  itemsOnPage,
  onPageChange,
}: PaginationProps) {
  return (
    <div className="flex flex-col items-center justify-between gap-4 border-t border-gray-200 px-4 py-4 sm:flex-row sm:px-6 dark:border-gray-800">
      <p className="text-sm text-gray-500 dark:text-gray-400">
        Hiển thị <span className="font-medium text-gray-200">{itemsOnPage}</span>{" "}
        trên <span className="font-medium text-gray-200">{totalItems}</span> kết
        quả
      </p>

      <div className="flex flex-wrap items-center justify-center gap-1 sm:gap-1.5">
        <button
          onClick={() => onPageChange(Math.max(1, currentPage - 1))}
          disabled={currentPage <= 1}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-800 text-gray-400 disabled:opacity-40 hover:bg-gray-800 transition"
        >
          <ArrowLeft className="h-4 w-4" />
        </button>

        {pageWindow(currentPage, totalPages).map((page, idx) =>
          page === null ? (
            <span key={`gap-${idx}`} className="flex h-9 w-6 items-center justify-center text-sm text-gray-400">
              …
            </span>
          ) : (
          <button
            key={page}
            onClick={() => onPageChange(page)}
            className={`flex h-9 w-9 items-center justify-center rounded-lg text-sm font-medium transition ${
              currentPage === page
                ? "bg-indigo-600 text-white"
                : "text-gray-400 hover:bg-gray-800"
            }`}
          >
            {page}
          </button>
          ),
        )}

        <button
          onClick={() => onPageChange(Math.min(totalPages, currentPage + 1))}
          disabled={currentPage >= totalPages}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-800 text-gray-400 disabled:opacity-40 hover:bg-gray-800 transition"
        >
          <ArrowRight className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}
