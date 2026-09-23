import React, { useState } from "react";
import { SlidersHorizontal, ArrowUpDown } from "lucide-react";
import SearchInput from "./SearchInput";
import Pagination from "./Pagination";

export interface Column<T> {
  header: string;
  accessorKey?: keyof T;
  render?: (item: T) => React.ReactNode;
  sortable?: boolean;
  align?: "left" | "center" | "right";
}

interface DataTableProps<T> {
  title: string;
  subtitle?: string;
  data: T[];
  columns: Column<T>[];
  rowKey: (item: T) => string | number;
  actionButton?: React.ReactNode;
  filters?: React.ReactNode;
  searchValue?: string;
  onSearch?: (keyword: string) => void;
  // Phân trang
  currentPage?: number;
  totalPages?: number;
  totalItems?: number;
  onPageChange?: (page: number) => void;
}

export default function DataTable<T>({
  title,
  subtitle,
  data = [],
  columns,
  rowKey,
  actionButton,
  filters,
  searchValue = "",
  onSearch,
  currentPage = 1,
  totalPages = 1,
  totalItems = 0,
  onPageChange,
}: DataTableProps<T>) {
  const [selectedKeys, setSelectedKeys] = useState<(string | number)[]>([]);

  const isAllSelected = data.length > 0 && selectedKeys.length === data.length;

  const toggleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.checked) {
      setSelectedKeys(data.map(rowKey));
    } else {
      setSelectedKeys([]);
    }
  };

  const toggleSelectRow = (key: string | number) => {
    setSelectedKeys((prev) =>
      prev.includes(key) ? prev.filter((item) => item !== key) : [...prev, key],
    );
  };

  return (
    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      {/* Header Toolbar */}
      <div className="flex flex-col justify-between gap-5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center dark:border-gray-800">
        <div>
          <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
            {title}
          </h3>
          {subtitle && (
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {subtitle}
            </p>
          )}
        </div>
        <div className="flex gap-3">
          <button className="shadow-theme-xs inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-medium text-gray-700 ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">
            Export
          </button>
          {actionButton}
        </div>
      </div>

      {/* Search & Filter Bar */}
      <div className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
        <SearchInput value={searchValue} onChange={(value) => onSearch?.(value)} />
        <div className="flex items-center gap-3">
          {filters}
          <button className="inline-flex items-center gap-2 rounded-lg border border-gray-800 bg-[#0e1726]/60 px-4 py-2 text-sm font-medium text-gray-300 hover:bg-gray-800 transition">
            <SlidersHorizontal className="h-4 w-4" />
            Bộ lọc
          </button>
        </div>
      </div>

      {/* Table Container */}
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm text-gray-400">
          <thead className="border-b border-gray-800/60 text-xs uppercase text-gray-400">
            <tr>
              <th className="px-6 py-4 w-12">
                <input
                  type="checkbox"
                  checked={isAllSelected}
                  onChange={toggleSelectAll}
                  className="h-4 w-4 rounded border-gray-700 bg-transparent text-indigo-600 focus:ring-0 cursor-pointer"
                />
              </th>
              {columns.map((col, idx) => (
                <th
                  key={idx}
                  className={`px-6 py-4 ${
                    col.align === "right"
                      ? "text-right"
                      : col.align === "center"
                        ? "text-center"
                        : ""
                  }`}
                >
                  <div
                    className={`flex items-center gap-1.5 ${
                      col.align === "right"
                        ? "justify-end"
                        : col.align === "center"
                          ? "justify-center"
                          : ""
                    } ${col.sortable ? "cursor-pointer select-none" : ""}`}
                  >
                    {col.header}
                    {col.sortable && <ArrowUpDown className="h-3.5 w-3.5" />}
                  </div>
                </th>
              ))}
            </tr>
          </thead>

          <tbody className="divide-y divide-gray-800/50 font-medium">
            {data.map((item) => {
              const id = rowKey(item);
              const isChecked = selectedKeys.includes(id);

              return (
                <tr key={id} className="transition hover:bg-white/[0.02]">
                  <td className="px-6 py-4">
                    <input
                      type="checkbox"
                      checked={isChecked}
                      onChange={() => toggleSelectRow(id)}
                      className="h-4 w-4 rounded border-gray-700 bg-transparent text-indigo-600 focus:ring-0 cursor-pointer"
                    />
                  </td>
                  {columns.map((col, cIdx) => (
                    <td
                      key={cIdx}
                      className={`px-6 py-4 ${
                        col.align === "right"
                          ? "text-right"
                          : col.align === "center"
                            ? "text-center"
                            : ""
                      }`}
                    >
                      {col.render
                        ? col.render(item)
                        : col.accessorKey
                          ? String(item[col.accessorKey] ?? "")
                          : null}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>

        <Pagination
          currentPage={currentPage}
          totalPages={totalPages}
          totalItems={totalItems || data.length}
          itemsOnPage={data.length}
          onPageChange={(page) => onPageChange?.(page)}
        />
      </div>
    </div>
  );
}
