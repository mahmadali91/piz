<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Auth;
use PIZ\Database;
use PIZ\View;

final class ActivityController
{
    public function index(): void
    {
        Auth::requirePermission('activity.view');

        $period = period_range();
        $action = trim((string) ($_GET['action'] ?? ''));
        $userId = (int) ($_GET['user_id'] ?? 0);
        $search = trim((string) ($_GET['q'] ?? ''));

        $conditions = ['l.created_at >= ?', 'l.created_at < ?'];
        $params = [$period['start'], $period['end']];
        if ($action !== '') {
            $conditions[] = 'l.action = ?';
            $params[] = $action;
        }
        if ($userId > 0) {
            $conditions[] = 'l.user_id = ?';
            $params[] = $userId;
        }
        if ($search !== '') {
            $conditions[] = '(l.details LIKE ? OR u.name LIKE ? OR l.ip LIKE ?)';
            $needle = '%' . $search . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }
        $where = implode(' AND ', $conditions);

        $perPage = 40;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM audit_log l LEFT JOIN users u ON u.id = l.user_id WHERE {$where}",
            $params
        );
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT l.*, u.name AS user_name, u.login AS user_login
             FROM audit_log l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE {$where}
             ORDER BY l.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $users = Database::fetchAll('SELECT id, name FROM users ORDER BY name');
        $actions = Database::fetchAll('SELECT action, COUNT(*) AS cnt FROM audit_log GROUP BY action ORDER BY cnt DESC');

        View::render('activity', [
            'title' => 'Журнал действий',
            'active' => 'activity',
            'user' => Auth::user(),
            'rows' => $rows,
            'users' => $users,
            'actions' => $actions,
            'filters' => ['action' => $action, 'user_id' => $userId, 'q' => $search],
            'period' => $period,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }
}
