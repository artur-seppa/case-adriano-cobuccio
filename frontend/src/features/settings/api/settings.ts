import { apiRequest, apiFetch } from "@/shared/api/client";

export function updateProfile(input: { name: string; email: string }): Promise<void> {
  return apiRequest<void>("/api/user/profile-information", { method: "PUT", body: JSON.stringify(input) });
}

export function updatePassword(input: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  return apiRequest<void>("/api/user/password", { method: "PUT", body: JSON.stringify(input) });
}

export interface SessionRow {
  id: string;
  ip_address: string | null;
  user_agent: string | null;
  last_active: string;
  is_current: boolean;
}

export function fetchSessions(): Promise<{ data: SessionRow[] }> {
  return apiFetch("/wallet/sessions");
}

export function revokeSession(id: string): Promise<void> {
  return apiFetch<void>(`/wallet/sessions/${id}`, { method: "DELETE" });
}
