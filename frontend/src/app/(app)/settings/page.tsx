import { ProfileForm } from "@/features/settings/components/ProfileForm";
import { PasswordForm } from "@/features/settings/components/PasswordForm";
import { SessionsList } from "@/features/settings/components/SessionsList";

export default function SettingsPage() {
  return (
    <div className="flex flex-col gap-6">
      <ProfileForm />
      <PasswordForm />
      <SessionsList />
    </div>
  );
}
