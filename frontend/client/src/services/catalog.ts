import { apiGet, apiPost } from "../libs/api";
import type {
  ApiListResponse,
  Category,
  PaginationMeta,
  Product,
  ProductDetail,
  ProductSort,
  ProductVariant,
  Store,
  StoreListItem,
  StoreSort,
  UseCase,
} from "../types/product";

/** Product thô từ API (price là chuỗi "37990000.00"). */
interface RawProduct extends Omit<Product, "price" | "final_price"> {
  price: string | number;
  final_price: string | number;
}

function normalizeProduct(raw: RawProduct): Product {
  return {
    ...raw,
    price: Number(raw.price),
    final_price: Number(raw.final_price),
    discount_percent: Number(raw.discount_percent) || 0,
    images: raw.images ?? [],
  };
}

/**
 * Lấy toàn bộ danh mục kèm thương hiệu (brands) trong 1 lần gọi.
 * Dùng để ProductSection tự tìm danh mục theo `code` và hiển thị brand thật.
 */
export async function getCategoriesWithBrands(): Promise<Category[]> {
  const res = await apiGet<ApiListResponse<Category>>("/categories", {
    per_page: 100,
    with_brands: 1,
  });
  return res.data;
}

/** Lấy danh sách sản phẩm thuộc một danh mục (dùng cho ProductSection). */
export async function getProductsByCategory(
  categoryId: number,
  perPage = 12,
  useCase?: string | null,
  provinceId?: number | null,
): Promise<Product[]> {
  const res = await apiGet<ApiListResponse<RawProduct>>("/products", {
    category_id: categoryId,
    useCase: useCase || undefined,
    province_id: provinceId || undefined,
    per_page: perPage,
  });
  return res.data.map(normalizeProduct);
}

/**
 * Lấy Quick Link đang hiển thị của một danh mục (image + tên).
 * Trả về [] khi lỗi để UI vẫn hiển thị bình thường.
 */
export async function getUseCasesByCategory(
  categoryId: number,
): Promise<UseCase[]> {
  const res = await apiGet<{ success: boolean; data: UseCase[] }>(
    "/use-cases",
    { categoryId },
  );
  return res.data ?? [];
}

/**
 * Lấy danh sách danh mục CẤP CAO NHẤT đang hiển thị (status = 1), không kèm
 * brands. Danh mục con (VD: dưới "Phụ Kiện") không nằm trong danh sách này —
 * dùng `getCategoriesWithChildren`/`getSubcategories` khi cần.
 */
export async function getCategories(): Promise<Category[]> {
  const res = await apiGet<ApiListResponse<Category>>("/categories", {
    per_page: 100,
    parent_id: "null",
  });
  return res.data.filter((category) => category.status === 1);
}

/**
 * Như `getCategories` nhưng kèm sẵn danh mục con (nếu có) của mỗi danh mục
 * cấp cao nhất — dùng cho sidebar hiện flyout khi hover.
 */
export async function getCategoriesWithChildren(): Promise<Category[]> {
  const res = await apiGet<ApiListResponse<Category>>("/categories", {
    per_page: 100,
    parent_id: "null",
    with_children: 1,
  });
  return res.data.filter((category) => category.status === 1);
}

/** Lấy danh mục con (status = 1) của một danh mục cha theo id. */
export async function getSubcategories(parentId: number): Promise<Category[]> {
  const res = await apiGet<ApiListResponse<Category>>("/categories", {
    per_page: 100,
    parent_id: parentId,
  });
  return res.data.filter((category) => category.status === 1);
}

/** Tìm một danh mục (kèm brands) theo slug. */
export async function getCategoryBySlug(
  slug: string,
): Promise<Category | undefined> {
  const categories = await getCategoriesWithBrands();
  return categories.find((category) => category.slug === slug);
}

/** Variant thô từ API (price/stock có thể là chuỗi). */
interface RawVariant {
  sku: string;
  attributes?: Record<string, string>;
  price: string | number;
  stock: string | number;
}

/** Product chi tiết thô từ API. */
interface RawProductDetail extends RawProduct {
  specifications?: { name: string; value: string }[];
  variants?: RawVariant[];
}

function normalizeVariant(raw: RawVariant): ProductVariant {
  return {
    sku: raw.sku,
    attributes: raw.attributes ?? {},
    price: Number(raw.price) || 0,
    stock: Number(raw.stock) || 0,
  };
}

/** Lấy chi tiết 1 sản phẩm theo slug hoặc id (kèm thông số + phiên bản). */
export async function getProductDetail(
  slugOrId: string | number,
): Promise<ProductDetail> {
  const res = await apiGet<{ success: boolean; data: RawProductDetail }>(
    `/products/${slugOrId}`,
  );
  const raw = res.data;

  return {
    ...normalizeProduct(raw),
    specifications: raw.specifications ?? [],
    variants: (raw.variants ?? []).map(normalizeVariant),
  };
}

/**
 * Sản phẩm gợi ý cùng danh mục (ngẫu nhiên, tối đa `limit`), bỏ chính sản phẩm hiện tại.
 */
export async function getSimilarProducts(
  categoryId: number,
  excludeId: number,
  limit = 5,
): Promise<Product[]> {
  const pool = await getProductsByCategory(categoryId, 20);
  const candidates = pool.filter((product) => product.id !== excludeId);

  // Xáo trộn nhẹ để mỗi lần vào hiển thị khác nhau.
  for (let i = candidates.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [candidates[i], candidates[j]] = [candidates[j], candidates[i]];
  }

  return candidates.slice(0, limit);
}

interface GetProductsParams {
  categoryId?: number;
  brandId?: number | null;
  storeId?: number | null;
  useCase?: string | null;
  /** Tỉnh/thành khách đang chọn ở header — chỉ hiện sản phẩm của gian hàng cùng tỉnh. */
  provinceId?: number | null;
  search?: string;
  sort?: ProductSort;
  page?: number;
  perPage?: number;
  isFeatured?: boolean;
  isFlashSale?: boolean;
}

/** Lấy sản phẩm có lọc category/brand/store/tỉnh/Quick Link/tìm kiếm + sắp xếp + phân trang. */
export async function getProducts({
  categoryId,
  brandId,
  storeId,
  useCase,
  provinceId,
  search,
  sort,
  page = 1,
  perPage = 20,
  isFeatured,
  isFlashSale,
}: GetProductsParams): Promise<{ products: Product[]; meta: PaginationMeta }> {
  const res = await apiGet<ApiListResponse<RawProduct>>("/products", {
    category_id: categoryId,
    brand_id: brandId ?? undefined,
    store_id: storeId ?? undefined,
    useCase: useCase || undefined,
    province_id: provinceId || undefined,
    search: search || undefined,
    is_featured: isFeatured ? 1 : undefined,
    is_flash_sale: isFlashSale ? 1 : undefined,
    sort,
    page,
    per_page: perPage,
  });
  return { products: res.data.map(normalizeProduct), meta: res.meta };
}

/** Giờ kết thúc flash sale hiện tại (đếm ngược ở trang chủ), null nếu chưa cấu hình. */
export async function getFlashSaleEndsAt(): Promise<string | null> {
  const res = await apiGet<{ success: boolean; data: { ends_at: string | null } }>(
    "/settings/flash-sale",
  );
  return res.data.ends_at;
}

/** Lấy thông tin một gian hàng theo slug. */
export async function getStoreBySlug(slug: string): Promise<Store> {
  const res = await apiGet<{ success: boolean; data: Store }>(
    `/stores/${slug}`,
  );
  return res.data;
}

/** Danh sách TẤT CẢ gian hàng (đang hoạt động) để khách khám phá — "Kênh người bán". */
export async function getStores({
  search,
  sort,
  provinceId,
  page = 1,
  perPage = 12,
}: {
  search?: string;
  sort?: StoreSort;
  /** Tỉnh/thành khách đang chọn ở header — chỉ hiện gian hàng có kho cùng tỉnh. */
  provinceId?: number | null;
  page?: number;
  perPage?: number;
} = {}): Promise<{ stores: StoreListItem[]; meta: PaginationMeta }> {
  const res = await apiGet<ApiListResponse<StoreListItem>>("/stores", {
    search: search || undefined,
    province_id: provinceId || undefined,
    sort,
    page,
    per_page: perPage,
  });
  return { stores: res.data, meta: res.meta };
}

/** Theo dõi / bỏ theo dõi 1 gian hàng (toggle) — cần đăng nhập. */
export async function toggleFollowStore(
  slug: string,
): Promise<{ following: boolean; followers_count: number }> {
  const res = await apiPost<{
    success: boolean;
    data: { following: boolean; followers_count: number };
  }>(`/stores/${slug}/follow`);
  return res.data;
}
