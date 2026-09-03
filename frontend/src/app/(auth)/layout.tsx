import { Card } from "@/shared/ui/Card";
import { Wordmark } from "@/shared/ui/Wordmark";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div
      className="flex min-h-screen flex-col items-center justify-center gap-6 px-4 py-12"
      style={{ background: "linear-gradient(135deg, #0E3D12 0%, #2E7D32 55%, #4CAF4F 100%)" }}
    >
      <div className="flex flex-col items-center gap-1 text-center">
        <Wordmark className="text-3xl text-white" />
        <p className="text-sm text-white/80">Deposite, transfira, acompanhe.</p>
      </div>
      <Card className="w-full max-w-sm p-6">{children}</Card>
    </div>
  );
}
