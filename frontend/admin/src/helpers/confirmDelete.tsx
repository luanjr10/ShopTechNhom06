import Swal from "sweetalert2";

interface ConfirmDeleteOptions {
  title?: string;
  successTitle?: string;
  successText?: string;
  errorTitle?: string;
  errorText?: string;
}

/**
 * Hiển thị hộp thoại xác nhận xóa, thực thi `action` nếu người dùng đồng ý,
 * rồi hiển thị thông báo kết quả. Dùng chung cho mọi màn hình quản lý (sản
 * phẩm, danh mục, ...).
 */
export async function confirmDelete(
  action: () => Promise<{ success?: boolean }>,
  options: ConfirmDeleteOptions = {},
): Promise<boolean> {
  const result = await Swal.fire({
    title: options.title ?? "Bạn có chắc muốn xóa mục này?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    cancelButtonText: "Hủy bỏ",
    confirmButtonText: "Đồng ý",
  });

  if (!result.isConfirmed) {
    return false;
  }

  try {
    const response = await action();

    if (response?.success) {
      Swal.fire({
        title: options.successTitle ?? "Đã xóa",
        text: options.successText ?? "Mục này đã được xóa",
        icon: "success",
      });
      return true;
    }

    Swal.fire({
      title: options.errorTitle ?? "Xóa không thành công",
      text: options.errorText ?? "Vui lòng kiểm tra lại và thử lại sau",
      icon: "error",
    });
    return false;
  } catch (error) {
    console.log(error);
    Swal.fire({
      title: options.errorTitle ?? "Xóa không thành công",
      text: options.errorText ?? "Vui lòng kiểm tra lại và thử lại sau",
      icon: "error",
    });
    return false;
  }
}
