import { ApiError } from "../problem";
import { fieldError, genericErrorMessage } from "../fieldError";

describe("fieldError", () => {
  it("returns the first message for a field present in a validation error", () => {
    const error = new ApiError({
      status: 422,
      type: "https://wallet.test/problems/validation-failed",
      title: "The given data was invalid.",
      errors: { email: ["The email has already been taken."] },
    });

    expect(fieldError(error, "email")).toBe("The email has already been taken.");
  });

  it("returns undefined for a field that has no error", () => {
    const error = new ApiError({
      status: 422,
      type: "https://wallet.test/problems/validation-failed",
      title: "The given data was invalid.",
      errors: { email: ["taken"] },
    });

    expect(fieldError(error, "password")).toBeUndefined();
  });

  it("returns undefined for a non-ApiError", () => {
    expect(fieldError(new TypeError("Failed to fetch"), "email")).toBeUndefined();
  });
});

describe("genericErrorMessage", () => {
  it("returns undefined when the ApiError carries field-keyed errors (handled by fieldError instead)", () => {
    const error = new ApiError({
      status: 422,
      type: "https://wallet.test/problems/validation-failed",
      title: "The given data was invalid.",
      errors: { email: ["taken"] },
    });

    expect(genericErrorMessage(error)).toBeUndefined();
  });

  it("returns the detail (falling back to title) for an ApiError without field errors", () => {
    const withDetail = new ApiError({
      status: 419,
      type: "about:blank",
      title: "CSRF token mismatch.",
      detail: "Sessão expirada, tente novamente.",
    });
    expect(genericErrorMessage(withDetail)).toBe("Sessão expirada, tente novamente.");

    const withoutDetail = new ApiError({ status: 500, type: "about:blank", title: "Request failed" });
    expect(genericErrorMessage(withoutDetail)).toBe("Request failed");
  });

  it("returns a network-error message for a non-ApiError error, e.g. a fetch TypeError", () => {
    expect(genericErrorMessage(new TypeError("Failed to fetch"))).toBe(
      "Erro de rede. Verifique sua conexão e tente novamente.",
    );
  });

  it("returns undefined when there is no error", () => {
    expect(genericErrorMessage(null)).toBeUndefined();
    expect(genericErrorMessage(undefined)).toBeUndefined();
  });
});
