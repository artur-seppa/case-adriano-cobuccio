import { NextRequest, NextResponse } from "next/server";

const SESSION_COOKIE = "carteira-financeira-session";
const AUTH_ONLY_PATHS = ["/login", "/register", "/forgot-password"];

export function middleware(request: NextRequest) {
  const hasSession = request.cookies.has(SESSION_COOKIE);
  const { pathname } = request.nextUrl;

  const isAuthPath = AUTH_ONLY_PATHS.some((path) => pathname.startsWith(path));

  if (!hasSession && !isAuthPath && pathname !== "/reset-password" && pathname !== "/verify-email") {
    return NextResponse.redirect(new URL("/login", request.url));
  }

  if (hasSession && isAuthPath) {
    return NextResponse.redirect(new URL("/", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next|favicon.ico|api|sanctum|docs).*)"],
};
