import { Button, FileInput, Label } from "flowbite-react";
import { Trash, X } from "lucide-react";

interface ImageManagerProps {
  mode: "create" | "edit";
  existingImages: string[];
  onRemoveExisting: (index: number) => void;
  newImages: (File | null)[];
  onNewImageChange: (index: number, fileList: FileList | null) => void;
  onAddSlot: () => void;
  onRemoveSlot: (index: number) => void;
}

/**
 * Quản lý ảnh cho form thêm/sửa: hiển thị ảnh hiện có (chế độ sửa, có thể
 * xóa từng ảnh) và danh sách ô chọn ảnh mới (thêm/bớt ô tùy ý).
 */
export default function ImageManager({
  mode,
  existingImages,
  onRemoveExisting,
  newImages,
  onNewImageChange,
  onAddSlot,
  onRemoveSlot,
}: ImageManagerProps) {
  return (
    <>
      {mode === "edit" && existingImages.length > 0 && (
        <div>
          <div className="mb-2 block">
            <Label>Ảnh Hiện Tại</Label>
          </div>

          <div className="flex flex-wrap gap-3">
            {existingImages.map((imageUrl, index) => (
              <div key={imageUrl} className="relative">
                <img
                  src={imageUrl}
                  alt={`Ảnh hiện tại ${index}`}
                  className="h-20 w-20 rounded-lg object-cover border border-slate-700"
                />
                <button
                  type="button"
                  onClick={() => onRemoveExisting(index)}
                  className="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full bg-rose-600 text-white cursor-pointer hover:bg-rose-700"
                >
                  <X className="h-3 w-3" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      <div>
        <div className="mb-2 flex items-center justify-between">
          <Label>{mode === "create" ? "Ảnh Sản Phẩm" : "Thêm Ảnh Mới"}</Label>

          <Button
            type="button"
            size="sm"
            onClick={onAddSlot}
            className="cursor-pointer"
          >
            + Thêm ảnh
          </Button>
        </div>

        <div className="space-y-3">
          {newImages.map((imageFile, index) => (
            <div
              key={index}
              className="flex items-end gap-3 rounded-lg border border-slate-700 p-3"
            >
              <div className="flex-1">
                <Label htmlFor={`image-${index}`}>
                  {index === 0 ? "Ảnh chính" : `Ảnh phụ ${index}`}
                </Label>

                <FileInput
                  id={`image-${index}`}
                  accept="image/*"
                  onChange={(e) => onNewImageChange(index, e.target.files)}
                />

                {imageFile && (
                  <img
                    src={URL.createObjectURL(imageFile)}
                    alt={`Preview ${index}`}
                    className="mt-2 h-24 w-24 rounded-lg object-cover border border-slate-700"
                  />
                )}
              </div>

              <Button
                type="button"
                color="failure"
                size="sm"
                onClick={() => onRemoveSlot(index)}
                disabled={newImages.length === 1}
                className="cursor-pointer"
              >
                <Trash className="h-4 w-4" />
              </Button>
            </div>
          ))}
        </div>
      </div>
    </>
  );
}
