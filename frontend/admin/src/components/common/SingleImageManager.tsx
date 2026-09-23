import { Button, FileInput, Label } from "flowbite-react";
import { Trash, X } from "lucide-react";

interface SingleImageManagerProps {
  mode: "create" | "edit";
  existingImage: string | null;
  newImage: File | null;
  onRemoveExisting: () => void;
  onNewImageChange: (fileList: FileList | null) => void;
}

export default function SingleImageManager({
  mode,
  existingImage,
  newImage,
  onRemoveExisting,
  onNewImageChange,
}: SingleImageManagerProps) {
  return (
    <div>
      <div className="mb-2 block">
        <Label>
          {mode === "create" ? "Logo Thương Hiệu" : "Logo Thương Hiệu"}
        </Label>
      </div>

      {/* Ảnh hiện tại */}
      {existingImage && !newImage && (
        <div className="mb-3">
          <div className="relative inline-block">
            <img
              src={existingImage}
              alt="Logo hiện tại"
              className="h-24 w-24 rounded-lg border border-slate-700 object-cover"
            />

            <button
              type="button"
              onClick={onRemoveExisting}
              className="absolute -right-2 -top-2 flex h-5 w-5 cursor-pointer items-center justify-center rounded-full bg-rose-600 text-white hover:bg-rose-700"
            >
              <X className="h-3 w-3" />
            </button>
          </div>
        </div>
      )}

      {/* Ảnh mới */}
      {newImage && (
        <div className="mb-3">
          <div className="relative inline-block">
            <img
              src={URL.createObjectURL(newImage)}
              alt="Logo mới"
              className="h-24 w-24 rounded-lg border border-slate-700 object-cover"
            />

            <button
              type="button"
              onClick={() => onNewImageChange(null)}
              className="absolute -right-2 -top-2 flex h-5 w-5 cursor-pointer items-center justify-center rounded-full bg-rose-600 text-white hover:bg-rose-700"
            >
              <X className="h-3 w-3" />
            </button>
          </div>
        </div>
      )}

      {/* Chọn ảnh */}
      {!newImage && (
        <FileInput
          accept="image/*"
          onChange={(e) => onNewImageChange(e.target.files)}
        />
      )}
    </div>
  );
}
