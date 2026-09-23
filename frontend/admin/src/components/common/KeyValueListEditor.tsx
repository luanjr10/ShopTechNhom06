import { Button, Label, TextInput } from "flowbite-react";
import { Trash } from "lucide-react";

export interface KeyValueItem {
  name: string;
  value: string;
}

interface KeyValueListEditorProps {
  title: string;
  items: KeyValueItem[];
  onChange: (index: number, field: keyof KeyValueItem, value: string) => void;
  onAdd: () => void;
  onRemove: (index: number) => void;
  addLabel?: string;
  keyLabel?: string;
  valueLabel?: string;
  keyPlaceholder?: string;
  valuePlaceholder?: string;
}

/**
 * Danh sách cặp tên/giá trị có thể thêm/bớt tùy ý — dùng cho thông số kỹ
 * thuật sản phẩm và các danh sách thuộc tính tương tự trong tương lai.
 */
export default function KeyValueListEditor({
  title,
  items,
  onChange,
  onAdd,
  onRemove,
  addLabel = "+ Thêm",
  keyLabel = "Tên",
  valueLabel = "Giá trị",
  keyPlaceholder,
  valuePlaceholder,
}: KeyValueListEditorProps) {
  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <Label>{title}</Label>

        <Button type="button" size="sm" onClick={onAdd} className="cursor-pointer">
          {addLabel}
        </Button>
      </div>

      <div className="space-y-3">
        {items.map((item, index) => (
          <div
            key={index}
            className="flex items-end gap-3 rounded-lg border border-slate-700 p-3"
          >
            <div className="flex-1">
              <Label htmlFor={`kv-name-${index}`}>{keyLabel}</Label>
              <TextInput
                id={`kv-name-${index}`}
                value={item.name}
                placeholder={keyPlaceholder}
                onChange={(e) => onChange(index, "name", e.target.value)}
              />
            </div>

            <div className="flex-1">
              <Label htmlFor={`kv-value-${index}`}>{valueLabel}</Label>
              <TextInput
                id={`kv-value-${index}`}
                value={item.value}
                placeholder={valuePlaceholder}
                onChange={(e) => onChange(index, "value", e.target.value)}
              />
            </div>

            <Button
              type="button"
              color="failure"
              size="sm"
              onClick={() => onRemove(index)}
              disabled={items.length === 1}
              className="cursor-pointer"
            >
              <Trash className="h-4 w-4" />
            </Button>
          </div>
        ))}
      </div>
    </div>
  );
}
