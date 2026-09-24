import { useEffect, useRef, useState } from "react";
import { ChevronDown, LayoutGrid } from "lucide-react";
import TabCategory from "../../tabcategory";
import { Button } from "../../ui/button";

/**
 * Nút "Danh Mục" ở header — bấm mở menu danh mục (tái dùng `TabCategory`, y
 * hệt sidebar ở trang chủ) kèm lớp phủ mờ/tối phần còn lại của trang để menu
 * nổi bật, giống hành vi menu danh mục thật của CellphoneS.
 */
export function CategoryMenu() {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const handleClickOutside = (e: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [open]);

  return (
    <div ref={rootRef} className="relative shrink-0">
      <Button
        onClick={() => setOpen((o) => !o)}
        aria-label="Danh mục"
        className="gap-1.5 bg-white/15 backdrop-blur hover:bg-white/25 cursor-pointer"
      >
        <LayoutGrid className="size-5" />
        <span className="hidden sm:inline">Danh Mục</span>
        <ChevronDown className={`hidden size-4 sm:block transition-transform ${open ? "rotate-180" : ""}`} />
      </Button>

      {open && (
        <>
          {/* Lớp phủ mờ/tối phần còn lại của trang — nằm dưới header (sticky,
              z-1000) nên header vẫn sáng rõ, chỉ nội dung bên dưới bị mờ đi. */}
          <div
            className="fixed inset-0 z-999 bg-black/50 backdrop-blur-[2px]"
            onClick={() => setOpen(false)}
            aria-hidden
          />
          {/* Mobile: giới hạn chiều cao + cuộn; bỏ flyout con (TabCategory tự ẩn
              flyout dưới lg vì màn cảm ứng không có hover). */}
          <div className="absolute left-0 top-full z-1001 mt-2 max-lg:max-h-[70vh] max-lg:overflow-y-auto max-lg:rounded-2xl">
            <TabCategory onNavigate={() => setOpen(false)} />
          </div>
        </>
      )}
    </div>
  );
}
