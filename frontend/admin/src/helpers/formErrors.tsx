import { ValidationErrors } from "../types/common.types";

/**
 * Lấy danh sách lỗi validate (theo field) từ một lỗi axios trả về từ backend
 * Laravel (status 422, body dạng { errors: { field: [message, ...] } }).
 */
export function parseValidationErrors(error: unknown): ValidationErrors {
  const response = (
    error as { response?: { data?: { errors?: ValidationErrors } } }
  )?.response;

  return response?.data?.errors ?? {};
}

export function firstError(
  errors: ValidationErrors,
  field: string,
): string | undefined {
  return errors[field]?.[0];
}
