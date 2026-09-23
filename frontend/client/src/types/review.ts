export interface ReviewUser {
  id: number;
  name: string;
  username: string;
  avatar_url: string | null;
}

export interface ProductReview {
  id: number;
  product_id: number;
  rating: number;
  comment: string | null;
  images: string[] | null;
  is_verified_purchase: boolean;
  created_at: string;
  user: ReviewUser;
}

export interface ReviewStats {
  average: number;
  count: number;
  breakdown: Record<string, number>;
}

export interface ProductReviewsResponse {
  success: boolean;
  data: {
    data: ProductReview[];
    current_page: number;
    last_page: number;
    total: number;
  };
  stats: ReviewStats;
}

/** "Hỏi & đáp" — BE luôn làm phẳng tối đa 2 cấp, `replies` chỉ có ở comment gốc. */
export interface ProductComment {
  id: number;
  product_id: number;
  parent_id: number | null;
  body: string;
  created_at: string;
  user: ReviewUser | null;
  is_admin: boolean;
  is_seller_of_store: boolean;
  replies?: ProductComment[];
}

/** "Hỏi & đáp" theo DANH MỤC (trang danh mục) — cùng khuôn mẫu ProductComment. */
export interface CategoryComment {
  id: number;
  category_id: number;
  parent_id: number | null;
  body: string;
  created_at: string;
  user: ReviewUser | null;
  is_admin: boolean;
  replies?: CategoryComment[];
}
