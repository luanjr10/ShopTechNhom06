import { ArrowLeft, ArrowRight } from "lucide-react";

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
    <div className="flex flex-col items-center justify-between gap-4 border-t border-gray-200 px-6 py-4 sm:flex-row dark:border-gray-800">
      <p className="text-sm text-gray-500 dark:text-gray-400">
        Hiển thị <span className="font-medium text-gray-200">{itemsOnPage}</span>{" "}
        trên <span className="font-medium text-gray-200">{totalItems}</span> kết
        quả
      </p>

      <div className="flex items-center gap-1.5">
        <button
          onClick={() => onPageChange(Math.max(1, currentPage - 1))}
          disabled={currentPage <= 1}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-800 text-gray-400 disabled:opacity-40 hover:bg-gray-800 transition"
        >
          <ArrowLeft className="h-4 w-4" />
        </button>

        {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
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
        ))}

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
