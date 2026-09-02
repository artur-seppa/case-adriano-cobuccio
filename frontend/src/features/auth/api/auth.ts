import { apiRequest } from "@/shared/api/client";

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  email_verified_at: string | null;
}

export function fetchSession(): Promise<AuthUser> {
  return apiRequest<AuthUser>("/api/user");
}

export function login(input: { email: string; password: string }): Promise<void> {
  return apiRequest<void>("/api/login", { method: "POST", body: JSON.stringify(input) });
}

export function register(
  input: { name: string; email: string; password: string; password_confirmation: string },
): Promise<{ user: AuthUser; requires_email_verification: boolean }> {
  return apiRequest("/api/register", { method: "POST", body: JSON.stringify(input) });
}

export function logout(): Promise<void> {
  return apiRequest<void>("/api/logout", { method: "POST" });
}

export function forgotPassword(input: { email: string }): Promise<void> {
  return apiRequest<void>("/api/forgot-password", { method: "POST", body: JSON.stringify(input) });
}

export function resetPassword(input: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  return apiRequest<void>("/api/reset-password", { method: "POST", body: JSON.stringify(input) });
}

export function resendVerification(): Promise<void> {
  return apiRequest<void>("/api/email/verification-notification", { method: "POST" });
}
