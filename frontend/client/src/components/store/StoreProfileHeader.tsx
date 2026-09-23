import { useState } from "react";
import {
  BadgeCheck,
  Check,
  Loader2,
  MessagesSquare,
  Plus,
  ShoppingBag,
  ShieldCheck,
  Share2,
  Star,
  Users,
  Package,
} from "lucide-react";
import { StoreLogo } from "./StoreLogo";
import { type Store } from "../../types/product";
import { toggleFollowStore } from "../../services/catalog";
import { useAuth } from "../../context/AuthContext";

interface StoreProfileHeaderProps {
  store: Store;
}

function Stat({
  icon: Icon,
  value,
  label,
}: {
  icon: typeof Star;
  value: React.ReactNode;
  label: string;
}) {
  return (
    <div className="flex min-w-0 items-center gap-2 px-4 py-3">
      <Icon className="size-5 shrink-0 text-primary500" />
      <div className="min-w-0">
        <div className="truncate font-sans text-[15px] font-bold text-gray-800">
          {value}
        </div>
        <div className="truncate font-sans text-[12px] text-gray-500">
          {label}
        </div>
      </div>
    </div>
  );
}

/** Header hồ sơ gian hàng: cover, logo lớn, cấp độ, nút hành động, hàng chỉ số. */
export function StoreProfileHeader({ store }: StoreProfileHeaderProps) {
  const { user } = useAuth();
  const [following, setFollowing] = useState(!!store.is_following);
  const [followersCount, setFollowersCount] = useState(store.stats?.followers ?? 0);
  const [followLoading, setFollowLoading] = useState(false);
  const stats = store.stats;
  const rating = stats?.rating ?? 0;

  const handleToggleFollow = async () => {
    if (!user || followLoading) return;
    setFollowLoading(true);
    try {
      const result = await toggleFollowStore(store.slug);
      setFollowing(result.following);
      setFollowersCount(result.followers_count);
    } finally {
      setFollowLoading(false);
    }
  };

  return (
    <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-[0_2px_16px_rgba(0,0,0,0.06)]">
      {/* Cover */}
      <div className="relative h-40 bg-linear-to-br from-gray-900 via-gray-800 to-primary500/60 sm:h-48">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(215,0,24,0.35),transparent_45%)]" />

        {/* Nút hành động */}
        <div className="absolute right-4 top-4 flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={handleToggleFollow}
            disabled={!user || followLoading}
            title={!user ? "Đăng nhập để theo dõi gian hàng" : undefined}
            className={`flex items-center gap-1.5 rounded-lg px-3.5 py-2 font-sans text-[13px] font-semibold transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-70 ${
              following
                ? "bg-white/20 text-white"
                : "bg-primary500 text-white hover:bg-primary300"
            }`}
          >
            {followLoading ? (
              <Loader2 className="size-4 animate-spin" />
            ) : following ? (
              <Check className="size-4" />
            ) : (
              <Plus className="size-4" />
            )}
            {following ? "Đang theo dõi" : "Theo dõi"}
          </button>
          <button className="flex items-center gap-1.5 rounded-lg bg-white/15 px-3.5 py-2 font-sans text-[13px] font-medium text-white backdrop-blur transition-colors hover:bg-white/25 cursor-pointer">
            <Share2 className="size-4" />
            Chia sẻ
          </button>
          <button className="flex items-center gap-1.5 rounded-lg bg-white/15 px-3.5 py-2 font-sans text-[13px] font-medium text-white backdrop-blur transition-colors hover:bg-white/25 cursor-pointer">
            <MessagesSquare className="size-4" />
            Thảo luận
          </button>
        </div>

        {/* Tên + handle + badge */}
        <div className="absolute bottom-4 left-4 right-4 pl-40 text-white">
          <div className="flex items-center gap-2">
            <h1 className="font-sans text-[26px] font-bold drop-shadow">
              {store.name}
            </h1>
            <BadgeCheck className="size-6 text-primary300" />
          </div>
          <div className="mt-1 flex flex-wrap items-center gap-2 text-[13px]">
            <span className="text-white/80">/{store.slug}</span>
            <span className="rounded-full bg-white/15 px-2 py-0.5 backdrop-blur">
              <span className="mr-1 inline-block size-1.5 rounded-full bg-emerald-400" />
              Online
            </span>
          </div>
        </div>
      </div>

      {/* Logo lớn (overlap) */}
      <div className="relative">
        <div className="absolute -top-16 left-5 rounded-full bg-white p-1.5 shadow-lg">
          <StoreLogo name={store.name} logo={store.logo} size={112} />
        </div>

        {/* Badge cấp độ + bảo hành (dưới, cạnh logo) */}
        <div className="flex flex-wrap items-center gap-2 pl-40 pr-5 pt-3">
          <span className="flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 font-sans text-[12px] font-semibold text-amber-600">
            <ShoppingBag className="size-3.5" />
            {stats?.seller_level ?? "New Seller"}
          </span>
          <span className="flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-sans text-[12px] font-semibold text-emerald-600">
            <ShieldCheck className="size-3.5" />
            Bảo hành 1 ngày
          </span>
        </div>

        {/* Hàng chỉ số */}
        <div className="mt-3 flex flex-wrap items-center divide-x divide-gray-100 border-t border-gray-100 pl-40">
          <Stat
            icon={Star}
            value={rating.toFixed(1)}
            label={`${stats?.rating_count ?? 0} đánh giá`}
          />
          <Stat
            icon={Users}
            value={followersCount}
            label="người theo dõi"
          />
          <Stat
            icon={Package}
            value={stats?.products_count ?? store.products_count ?? 0}
            label="sản phẩm"
          />
          <Stat
            icon={ShoppingBag}
            value={stats?.completed_orders ?? 0}
            label="đơn hoàn thành"
          />
        </div>
      </div>
    </div>
  );
}
