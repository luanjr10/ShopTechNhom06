/** Cắt chuỗi về tối đa `maxLength` ký tự, thêm "..." nếu bị cắt bớt. */
export function truncateText(
  text: string | null | undefined,
  maxLength: number,
): string {
  if (!text) return "";
  return text.length > maxLength ? `${text.slice(0, maxLength)}...` : text;
}
