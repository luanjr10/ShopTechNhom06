export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ApiListResponse<T> {
  success: boolean;
  data: T[];
  meta: PaginationMeta;
}

/** Thương hiệu đổ từ bảng `brands` (qua quan hệ category_brand). */
export interface Brand {
  id: number;
  code: string;
  name: string;
  logo: string | null;
}

/** Danh mục đổ từ bảng `categories`, kèm brands khi gọi with_brands=1. */
export interface Category {
  id: number;
  parent_id?: number | null;
  code: string;
  name: string;
  slug: string;
  description: string | null;
  icon: string | null;
  color: string | null;
  /** "icon" (mặc định, dùng icon+color) hoặc "image" (ảnh thật upload Cloudinary). */
  display_type?: "icon" | "image";
  /** Ảnh đại diện danh mục (khi display_type = "image"), lấy từ MongoDB. */
  image?: string | null;
  status: number;
  status_order?: number;
  products_count?: number;
  children_count?: number;
  /** Danh mục con — chỉ có khi gọi kèm `with_children=1`. */
  children?: Category[];
  brands?: Brand[];
}

/** Gian hàng (rút gọn) gắn kèm sản phẩm. */
export interface StoreRef {
  id: number;
  name: string;
  slug: string;
  logo: string | null;
}

/** Thống kê người bán hiển thị ở trang gian hàng. */
export interface StoreStats {
  completed_orders: number;
  total_orders: number;
  orders_30d: number;
  completion_rate: number | null;
  complaint_rate: number;
  rating: number | null;
  rating_count: number;
  followers: number;
  products_count: number;
  seller_level: string;
}

/** Một dòng trong danh sách "Kênh người bán" (khám phá tất cả gian hàng). */
export interface StoreListItem extends StoreRef {
  description: string | null;
  products_count: number;
  followers_count: number;
  reviews_count: number;
  rating: number;
  created_at: string;
}

/** Kiểu sắp xếp danh sách gian hàng. */
export type StoreSort = "newest" | "products_desc" | "followers_desc" | "name_asc";

/** Gian hàng đầy đủ ở trang gian hàng. */
export interface Store extends StoreRef {
  description: string | null;
  status: string;
  products_count?: number;
  joined_at?: string;
  seller_profile?: { id: number; display_name: string } | null;
  categories?: { id: number; name: string; slug: string }[];
  stats?: StoreStats;
  /** Người đang xem có đang theo dõi gian hàng này không (null nếu chưa đăng nhập — BE tự nhận diện qua cookie JWT). */
  is_following?: boolean;
}

/** Sản phẩm đã chuẩn hoá (price ép về number) để dùng trong UI. */
export interface Product {
  id: number;
  code: string;
  name: string;
  slug: string;
  price: number;
  discount_percent: number;
  is_featured?: boolean;
  is_flash_sale?: boolean;
  stock: number;
  status: number;
  category_id: number;
  store_id?: number | null;
  store?: StoreRef | null;
  images: string[];
  thumbnail: string | null;
  final_price: number;
  rating?: number;
  reviews_count?: number;
}

/** Một dòng thông số kỹ thuật chung. */
export interface ProductSpecItem {
  name: string;
  value: string;
}

/** Các thuộc tính chọn được của một phiên bản (chỉ có khi thực sự tồn tại). */
export interface ProductVariantAttributes {
  color?: string;
  storage?: string;
  ram?: string;
  cpu?: string;
}

/** Một phiên bản (variant) người dùng có thể chọn, có giá/tồn kho/SKU riêng. */
export interface ProductVariant {
  sku: string;
  attributes: ProductVariantAttributes;
  price: number;
  stock: number;
}

/** Sản phẩm đầy đủ ở trang chi tiết (kèm thông số + phiên bản). */
export interface ProductDetail extends Product {
  specifications: ProductSpecItem[];
  variants: ProductVariant[];
}

/** Link nhanh (dữ liệu tĩnh: Cho trẻ em, Chơi game, ...). */
export interface CategoryQuickLink {
  title: string;
  icon: string;
}

/** Quick Link (nhu cầu sử dụng) lấy từ API, gắn với một danh mục. */
export interface UseCase {
  id: string;
  categoryId: number;
  name: string;
  slug: string;
  image: string;
  sortOrder: number;
  status: boolean;
}

/** Cấu hình 1 tab danh mục chính của ProductSection. */
export interface MainCategoryRef {
  /** Mã danh mục trùng với cột `code` trong DB (VD: CATE-02). */
  code: string;
  /** Nhãn hiển thị trên tab. */
  label: string;
}

/** Các kiểu sắp xếp sản phẩm mà backend hỗ trợ. */
export type ProductSort = "price_desc" | "price_asc" | "discount_desc";
