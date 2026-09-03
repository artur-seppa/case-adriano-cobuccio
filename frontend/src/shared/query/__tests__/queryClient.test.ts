import { createQueryClient } from "../queryClient";
import { ApiError } from "@/shared/api/problem";

it("does not retry on an ApiError (4xx/5xx from the API)", () => {
  const client = createQueryClient();
  const shouldRetry = client.getDefaultOptions().queries?.retry as (count: number, error: unknown) => boolean;

  expect(shouldRetry(0, new ApiError({ status: 422, type: "x", title: "x" }))).toBe(false);
});

it("retries a plain network error up to 2 times", () => {
  const client = createQueryClient();
  const shouldRetry = client.getDefaultOptions().queries?.retry as (count: number, error: unknown) => boolean;

  expect(shouldRetry(0, new TypeError("Failed to fetch"))).toBe(true);
  expect(shouldRetry(2, new TypeError("Failed to fetch"))).toBe(false);
});
