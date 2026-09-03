import type { Metadata } from "next";
import { Inter } from "next/font/google";
import { Providers } from "./providers";
import { ToastProvider } from "@/shared/ui/Toast";
import { AuthEventBridge } from "@/features/auth/AuthEventBridge";
import "./globals.css";

const inter = Inter({ subsets: ["latin"], variable: "--font-sans" });

export const metadata: Metadata = {
  title: "Carteira financeira",
  description: "Deposite, transfira e acompanhe seu saldo em tempo real.",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="pt-BR" className={inter.variable}>
      <body>
        <Providers>
          <ToastProvider>
            <AuthEventBridge />
            {children}
          </ToastProvider>
        </Providers>
      </body>
    </html>
  );
}
