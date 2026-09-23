import { resolveCategoryIcon } from "../../libs/categoryIcon";
import { type Category } from "../../types/product";

interface CategoryVisualProps {
  category: Pick<Category, "icon" | "color" | "display_type" | "image" | "name">;
  size?: number;
  className?: string;
  /** Ép icon dùng 1 màu cố định (bỏ qua `category.color` từ DB) — dùng ở nơi
   * cần bộ icon đồng bộ 1 tông, VD sidebar danh mục trang chủ. */
  color?: string;
}

/**
 * Hiển thị danh mục bằng ảnh thật (Cloudinary, `display_type: "image"`) nếu
 * có, ngược lại dùng icon+màu như trước — dùng chung ở sidebar, flyout và
 * lưới "Sắm thêm phụ kiện" để không lặp logic if/else ở nhiều nơi.
 */
export function CategoryVisual({ category, size = 20, className, color }: CategoryVisualProps) {
  if (category.display_type === "image" && category.image) {
    return (
      <img
        src={category.image}
        alt={category.name}
        className="size-full rounded-lg object-cover"
      />
    );
  }

  const Icon = resolveCategoryIcon(category.icon);
  const resolvedColor = color ?? category.color;
  return (
    <Icon
      size={size}
      className={className}
      style={resolvedColor ? { color: resolvedColor } : undefined}
    />
  );
}
