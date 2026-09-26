import { useEffect, useState } from "react";
import { PaginationMeta } from "../types/common.types";

const EMPTY_META: PaginationMeta = {
  current_page: 1,
  last_page: 1,
  per_page: 6,
  total: 0,
};

interface FetcherParams<TSort extends string> {
  page: number;
  sort: TSort;
  search?: string;
}

interface FetcherResult<T> {
  data: T[];
  meta: PaginationMeta;
}

export function usePaginatedResource<T, TSort extends string>(
  fetcher: (params: FetcherParams<TSort>) => Promise<FetcherResult<T> | undefined>,
  defaultSort: TSort,
  resetKey?: unknown,
) {
  const [items, setItems] = useState<T[]>([]);
  const [meta, setMeta] = useState<PaginationMeta>(EMPTY_META);
  const [page, setPage] = useState(1);
  const [sort, setSort] = useState<TSort>(defaultSort);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 400);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, sort, resetKey]);

  const refetch = async () => {
    const response = await fetcher({
      page,
      sort,
      search: debouncedSearch || undefined,
    });
    setItems(response?.data || []);
    setMeta(response?.meta || EMPTY_META);
  };

  useEffect(() => {
    refetch();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, sort, debouncedSearch, resetKey]);

  return {
    items,
    meta,
    page,
    setPage,
    sort,
    setSort,
    search,
    setSearch,
    refetch,
  };
}
