import { ValidationErrors } from "../types/common.types";

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
