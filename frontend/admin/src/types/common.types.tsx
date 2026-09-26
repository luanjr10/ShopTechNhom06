export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface SortOption<TSort extends string> {
  value: TSort;
  label: string;
}

export type ValidationErrors = Record<string, string[]>;
