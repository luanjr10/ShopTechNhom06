import { Eye, Link2, SquarePen, Trash } from "lucide-react";

interface RowActionsProps {
  onView?: () => void;
  onEdit?: () => void;
  onDelete?: () => void;
  /** Thao tác phụ (VD: quản lý Quick Link của danh mục). */
  onManage?: () => void;
  manageTitle?: string;
}

export default function RowActions({
  onView,
  onEdit,
  onDelete,
  onManage,
  manageTitle = "Quản lý",
}: RowActionsProps) {
  return (
    <div className="flex items-center justify-center gap-2">
      {onManage && (
        <button
          type="button"
          title={manageTitle}
          className="cursor-pointer text-gray-400 hover:text-indigo-400 transition"
          onClick={onManage}
        >
          <Link2 className="h-4 w-4" />
        </button>
      )}
      {onView && (
        <button
          type="button"
          title="Xem chi tiết"
          className="cursor-pointer text-gray-400 hover:text-white transition"
          onClick={onView}
        >
          <Eye className="h-4 w-4" />
        </button>
      )}
      {onEdit && (
        <button
          type="button"
          title="Chỉnh sửa"
          className="cursor-pointer text-gray-400 hover:text-amber-400 transition"
          onClick={onEdit}
        >
          <SquarePen className="h-4 w-4" />
        </button>
      )}
      {onDelete && (
        <button
          type="button"
          title="Xóa"
          className="cursor-pointer text-gray-400 hover:text-rose-400 transition"
          onClick={onDelete}
        >
          <Trash className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}
