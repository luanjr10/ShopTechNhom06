import { toast, Bounce } from "react-toastify";

const TOAST_OPTIONS = {
  position: "top-right" as const,
  autoClose: 5000,
  hideProgressBar: false,
  closeOnClick: false,
  pauseOnHover: true,
  draggable: true,
  theme: "light" as const,
  transition: Bounce,
};

export const notifySuccess = (message: string) => {
  toast.success(message, TOAST_OPTIONS);
};

export const notifyError = (message: string) => {
  toast.error(message, TOAST_OPTIONS);
};
