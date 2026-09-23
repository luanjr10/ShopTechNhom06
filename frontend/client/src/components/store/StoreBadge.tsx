import { Link } from "react-router-dom";
import { StoreLogo } from "./StoreLogo";
import { type StoreRef } from "../../types/product";

interface StoreBadgeProps {
  store: StoreRef;
  /** Cho phép bấm để sang trang gian hàng (mặc định false — tránh <a> lồng <a>). */
  asLink?: boolean;
  logoSize?: number;
  className?: string;
}

/** Chip hiển thị gian hàng: logo + tên. Dùng ở card, trang chi tiết, header gian hàng. */
export function StoreBadge({
  store,
  asLink = false,
  logoSize = 24,
  className = "",
}: StoreBadgeProps) {
  const content = (
    <>
      <StoreLogo name={store.name} logo={store.logo} size={logoSize} />
      <span className="truncate font-sans text-[13px] font-medium text-gray-600 group-hover/store:text-primary500">
        {store.name}
      </span>
    </>
  );

  const base = `group/store flex min-w-0 items-center gap-1.5 ${className}`;

  if (asLink) {
    return (
      <Link
        to={`/gian-hang/${store.slug}`}
        className={`${base} cursor-pointer`}
        onClick={(e) => e.stopPropagation()}
      >
        {content}
      </Link>
    );
  }

  return <div className={base}>{content}</div>;
}
