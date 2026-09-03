"use client";

import { Card } from "@/shared/ui/Card";
import { Table } from "@/shared/ui/Table";
import { Button } from "@/shared/ui/Button";
import { Badge } from "@/shared/ui/Badge";
import { Skeleton } from "@/shared/ui/Skeleton";
import { useSessions } from "../hooks/useSessions";
import { useRevokeSession } from "../hooks/useRevokeSession";

export function SessionsList() {
  const sessions = useSessions();
  const revoke = useRevokeSession();

  if (sessions.isLoading) {
    return (
      <Card>
        <Skeleton className="h-24" />
      </Card>
    );
  }

  return (
    <Card className="p-0">
      <div className="px-5 py-4">
        <h2 className="text-sm font-semibold text-ink-900">Sessões ativas</h2>
      </div>
      <Table>
        <Table.Head>
          <Table.Row>
            <Table.HeadCell>IP</Table.HeadCell>
            <Table.HeadCell>Dispositivo</Table.HeadCell>
            <Table.HeadCell>Última atividade</Table.HeadCell>
            <Table.HeadCell />
          </Table.Row>
        </Table.Head>
        <Table.Body>
          {sessions.data?.data.map((row) => (
            <Table.Row key={row.id}>
              <Table.Cell>{row.ip_address ?? "—"}</Table.Cell>
              <Table.Cell className="max-w-xs truncate">{row.user_agent ?? "—"}</Table.Cell>
              <Table.Cell>{new Date(row.last_active).toLocaleString("pt-BR")}</Table.Cell>
              <Table.Cell>
                {row.is_current ? (
                  <Badge variant="success">Esta sessão</Badge>
                ) : (
                  <Button
                    variant="secondary"
                    loading={revoke.isPending}
                    onClick={() => revoke.mutate(row.id)}
                  >
                    Revogar
                  </Button>
                )}
              </Table.Cell>
            </Table.Row>
          ))}
        </Table.Body>
      </Table>
    </Card>
  );
}
