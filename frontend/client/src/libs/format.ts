const currency = new Intl.NumberFormat("vi-VN");

/** Định dạng số tiền kiểu Việt Nam: 31790000 -> "31.790.000đ". */
export function formatPrice(value: number): string {
  return `${currency.format(Math.round(value || 0))}đ`;
}

const dateFormatter = new Intl.DateTimeFormat("vi-VN", {
  day: "2-digit",
  month: "2-digit",
  year: "numeric",
});

/** Định dạng ngày dự kiến giao hàng — chỉ cần ngày, không cần giờ. */
export function formatExpectedDate(value: string | null): string | null {
  if (!value) return null;
  return dateFormatter.format(new Date(value));
}
