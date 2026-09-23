import { useEffect, useState } from "react";

interface ImageGalleryProps {
  images: string[];
  alt: string;
}

/**
 * Ảnh lớn + dải ảnh thu nhỏ để chọn ảnh xem, dùng cho các modal xem chi tiết.
 */
export default function ImageGallery({ images, alt }: ImageGalleryProps) {
  const [activeImage, setActiveImage] = useState(0);

  useEffect(() => {
    setActiveImage(0);
  }, [images]);

  return (
    <div>
      <div className="aspect-square w-full overflow-hidden rounded-xl border border-slate-800 bg-[#0e1726]">
        {images[activeImage] ? (
          <img
            src={images[activeImage]}
            alt={alt}
            className="h-full w-full object-cover"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-sm text-slate-600">
            Không có ảnh
          </div>
        )}
      </div>

      {images.length > 1 && (
        <div className="mt-3 flex gap-2 overflow-x-auto pb-1">
          {images.map((image, index) => (
            <button
              type="button"
              key={index}
              onClick={() => setActiveImage(index)}
              className={`h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition ${
                index === activeImage
                  ? "border-indigo-500"
                  : "border-slate-800 opacity-70 hover:opacity-100"
              }`}
            >
              <img
                src={image}
                alt={`${alt}-${index}`}
                className="h-full w-full object-cover"
              />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
