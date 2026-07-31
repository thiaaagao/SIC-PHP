<?php

class TeamsNotification
{
    public static function sendNewTicket(array $ticket): bool
    {
        $subcategory = $ticket['subcategory'] ?? 'N/A';
        $description = $ticket['description'] ?? '';
        $ip = $ticket['ip'] ?? 'N/A';
        $hostname = $ticket['hostname'] ?? 'N/A';
        $setor = $ticket['setor'] ?? 'N/A';
        $conf = $ticket['conf'] ?? 'N/A';
        $userName = $ticket['user_name'] ?? 'N/A';
        $ticketId = $ticket['id'] ?? 'N/A';
        $link = BASE_URL . "/ticket.php?id=" . $ticketId;

        $card = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => '0076D7',
            'summary' => "Novo P.S. #{$ticketId} - {$subcategory}",
            'sections' => [
                [
                    'activityTitle' => "Novo P.S. Aberto #{$ticketId}",
                    'activitySubtitle' => "Categoria: Suporte Técnico / {$subcategory}",
                    'facts' => [
                        ['name' => 'Solicitante', 'value' => $userName],
                        ['name' => 'Conf', 'value' => $conf],
                        ['name' => 'Hostname', 'value' => $hostname],
                        ['name' => 'IP', 'value' => $ip],
                        ['name' => 'Descricao', 'value' => $description],
                    ],
                    'potentialAction' => [
                        [
                            '@type' => 'OpenUri',
                            'name' => 'Ver P.S.',
                            'targets' => [['os' => 'default', 'uri' => $link]],
                        ],
                    ],
                ],
            ],
        ];

        return self::send($card);
    }

    public static function sendResolved(array $ticket): bool
    {
        $ticketId = $ticket['id'] ?? 'N/A';
        $userName = $ticket['user_name'] ?? 'N/A';
        $resolvedBy = $ticket['resolved_by'] ?? 'Suporte';
        $description = $ticket['description'] ?? '';
        $link = BASE_URL . "/ticket.php?id=" . $ticketId;

        $card = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => '28A745',
            'summary' => "P.S. #{$ticketId} Resolvido",
            'sections' => [
                [
                    'activityTitle' => "P.S. Resolvido #{$ticketId}",
                    'activitySubtitle' => "Resolvido por: {$resolvedBy}",
                    'facts' => [
                        ['name' => 'Solicitante', 'value' => $userName],
                        ['name' => 'Problema', 'value' => $description],
                    ],
                    'potentialAction' => [
                        [
                            '@type' => 'OpenUri',
                            'name' => 'Ver P.S.',
                            'targets' => [['os' => 'default', 'uri' => $link]],
                        ],
                    ],
                ],
            ],
        ];

        return self::send($card);
    }

    private static function send(array $card): bool
    {
        $payload = json_encode($card);
        $ch = curl_init(TEAMS_WEBHOOK_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}
