let inFlight: Promise<void> | null = null;

export function ensureCsrf(): Promise<void> {
  if (!inFlight) {
    inFlight = fetch("/sanctum/csrf-cookie", { credentials: "include" })
      .then(() => undefined)
      .finally(() => {
        inFlight = null;
      });
  }
  return inFlight;
}

export function readXsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : null;
}
