import { Select } from "flowbite-react";
import { SortOption } from "../../types/common.types";

interface SortSelectProps<TSort extends string> {
  value: TSort;
  onChange: (value: TSort) => void;
  options: SortOption<TSort>[];
  className?: string;
}

export default function SortSelect<TSort extends string>({
  value,
  onChange,
  options,
  className = "w-56",
}: SortSelectProps<TSort>) {
  return (
    <Select
      value={value}
      onChange={(e) => onChange(e.target.value as TSort)}
      className={className}
    >
      {options.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </Select>
  );
}
