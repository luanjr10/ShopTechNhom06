import { Button, Modal, ModalBody, ModalHeader } from "flowbite-react";
import { X } from "lucide-react";

interface FormModalProps {
  open: boolean;
  onClose: () => void;
  title: string;
  onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
  submitLabel: string;
  formKey?: string | number;
  initialFocusRef?: React.MutableRefObject<HTMLElement | null>;
  size?: "md" | "lg" | "xl" | "2xl" | "3xl" | "4xl";
  children: React.ReactNode;
}

/**
 * Khung modal dùng chung cho các form thêm/sửa (sản phẩm, danh mục, ...):
 * tiêu đề + nút đóng, nội dung form tùy biến qua `children`, và hàng nút
 * submit/hủy bỏ ở cuối.
 */
export default function FormModal({
  open,
  onClose,
  title,
  onSubmit,
  submitLabel,
  formKey,
  initialFocusRef,
  size = "3xl",
  children,
}: FormModalProps) {
  return (
    <Modal
      show={open}
      size={size}
      popup
      onClose={onClose}
      initialFocus={initialFocusRef}
    >
      <ModalHeader />
      <ModalBody>
        <form onSubmit={onSubmit} key={formKey}>
          <div className="space-y-6">
            <div className="flex flex-row items-center justify-between pb-4 border-b border-slate-800">
              <h3 className="text-xl font-semibold text-gray-900 dark:text-white">
                {title}
              </h3>

              <button
                type="button"
                className="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 cursor-pointer"
                onClick={onClose}
              >
                <X className="h-5 w-5 transition duration-150 ease-in-out hover:rotate-45" />
              </button>
            </div>

            {children}

            <div className="flex flex-row gap-2 items-center justify-end">
              <Button className="w-30 cursor-pointer" type="submit">
                {submitLabel}
              </Button>
              <Button
                type="button"
                onClick={onClose}
                className="w-30 cursor-pointer transition duration-150 ease-in-out"
              >
                Hủy Bỏ
              </Button>
            </div>
          </div>
        </form>
      </ModalBody>
    </Modal>
  );
}
