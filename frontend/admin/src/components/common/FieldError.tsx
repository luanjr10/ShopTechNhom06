interface FieldErrorProps {
  message?: string;
}

/** Dòng lỗi hiển thị bên dưới 1 ô input khi validate thất bại. */
export default function FieldError({ message }: FieldErrorProps) {
  if (!message) return null;

  return <p className="mt-1.5 text-xs font-medium text-rose-500">{message}</p>;
}
