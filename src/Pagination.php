<?php

class Pagination
{
    public static function getParams(int $perPage = 25): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;
        return ['page' => $page, 'perPage' => $perPage, 'offset' => $offset];
    }

    public static function getTotal(PDO $db, string $sql, array $params = []): int
    {
        $countSql = "SELECT COUNT(*) FROM ($sql) _cnt";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function render(int $currentPage, int $totalPages, string $baseUrl): string
    {
        if ($totalPages <= 1) return '';

        $sep = str_contains($baseUrl, '?') ? '&' : '?';
        $safeUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
        $html = '<nav><ul class="pagination pagination-sm mb-0 justify-content-center">';

        if ($currentPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $safeUrl . $sep . 'page=1">&laquo;</a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . $safeUrl . $sep . 'page=' . ($currentPage - 1) . '">&lsaquo;</a></li>';
        }

        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);

        if ($start > 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $currentPage ? ' active' : '';
            $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $safeUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
        }

        if ($end < $totalPages) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        if ($currentPage < $totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $safeUrl . $sep . 'page=' . ($currentPage + 1) . '">&rsaquo;</a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . $safeUrl . $sep . 'page=' . $totalPages . '">&raquo;</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }

    public static function info(int $total, int $offset, int $perPage): string
    {
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);
        return "Mostrando $from-$to de $total";
    }
}
