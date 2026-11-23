<?php
declare(strict_types=1);

namespace PIZ;

final class Audit
{
    public static function log(string $action, mixed $details = null): void
    {
        if (!Database::isConnected()) {
            return;
        }
        $payload = null;
        if ($details !== null) {
            $payload = is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        try {
            Database::query(
                'INSERT INTO audit_log (user_id, action, details, ip, created_at) VALUES (?, ?, ?, ?, ?)',
                [Auth::id() ?: null, $action, $payload, Auth::ip(), now()]
            );
        } catch (\Throwable $e) {
            error_log('[PIZ] audit failed: ' . $e->getMessage());
        }
    }
}
