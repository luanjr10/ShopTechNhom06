import { Construction } from "lucide-react";

interface ComingSoonProps {
  title: string;
  description?: string;
}

/** Trang giữ chỗ cho các mục chưa triển khai (Tin nhắn, Cài đặt...). */
export default function ComingSoon({ title, description }: ComingSoonProps) {
  return (
    <div className="flex flex-col gap-6 px-4 py-6 sm:gap-8 sm:px-6 sm:py-8 lg:px-10 lg:py-10">
      <h2 className="font-sans text-2xl font-bold text-white">{title}</h2>

      <div className="flex flex-col items-center justify-center gap-4 rounded-xl border border-gray-200 bg-white px-6 py-20 text-center dark:border-gray-800 dark:bg-white/[0.03]">
        <div className="flex size-16 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-400">
          <Construction className="size-8" />
        </div>
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">
          Tính năng đang được phát triển
        </h3>
        <p className="max-w-sm text-sm text-gray-500 dark:text-gray-400">
          {description ??
            `Mục "${title}" sẽ sớm ra mắt. Cảm ơn bạn đã kiên nhẫn chờ đợi.`}
        </p>
      </div>
    </div>
  );
}
