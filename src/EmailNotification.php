<?php

class EmailNotification
{
    private static function getAdminEmails(): array
    {
        $db = Database::getInstance();
        try {
            $stmt = $db->query("SELECT email FROM users WHERE role IN ('admin','suporte_ti') AND email IS NOT NULL AND email != ''");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    private static function send(string $to, string $subject, string $body): bool
    {
        $headers = "From: S.I.C. <noreply@sistema.com.br>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return @mail($to, $subject, $body, $headers);
    }

    public static function notifyNewTicket(array $ticket): void
    {
        $emails = self::getAdminEmails();
        if (!$emails) return;

        $subject = "[P.S.] Novo chamado {$ticket['code']}";
        $body = "
        <h3>Novo chamado aberto</h3>
        <p><strong>Codigo:</strong> {$ticket['code']}</p>
        <p><strong>Solicitante:</strong> {$ticket['requester_name']}</p>
        <p><strong>Categoria:</strong> {$ticket['subcategory']}</p>
        <p><strong>Prioridade:</strong> {$ticket['priority']}</p>
        <p><strong>Descricao:</strong> {$ticket['description']}</p>
        <p><a href='" . BASE_URL . "/ticket.php?id={$ticket['id']}'>Ver chamado</a></p>";

        foreach ($emails as $email) {
            self::send($email, $subject, $body);
        }
    }

    public static function notifyAssigned(array $ticket, string $assigneeName, string $assigneeEmail = ''): void
    {
        if (!$assigneeEmail) return;

        $subject = "[P.S.] Chamado {$ticket['code']} atribuido a voce";
        $body = "
        <h3>Chamado atribuido</h3>
        <p>O chamado <strong>{$ticket['code']}</strong> foi atribuido a voce.</p>
        <p><strong>Solicitante:</strong> {$ticket['requester_name']}</p>
        <p><strong>Problema:</strong> {$ticket['description']}</p>
        <p><a href='" . BASE_URL . "/ticket.php?id={$ticket['id']}'>Ver chamado</a></p>";

        self::send($assigneeEmail, $subject, $body);
    }

    public static function notifyResolved(array $ticket, string $resolverName): void
    {
        $emails = self::getAdminEmails();
        if (!$emails) return;

        $subject = "[P.S.] Chamado {$ticket['code']} resolvido";
        $body = "
        <h3>Chamado resolvido</h3>
        <p>O chamado <strong>{$ticket['code']}</strong> foi resolvido por {$resolverName}.</p>
        <p><strong>Solicitante:</strong> {$ticket['requester_name']}</p>
        <p><a href='" . BASE_URL . "/ticket.php?id={$ticket['id']}'>Ver chamado</a></p>";

        foreach ($emails as $email) {
            self::send($email, $subject, $body);
        }
    }
}
