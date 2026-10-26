<?php

declare(strict_types=1);

namespace App\Models;

/** Catatan tindakan admin. Tidak menyimpan password maupun data kesehatan. */
final class AuditLog extends Model
{
    public function record(?int $adminId, string $action, ?int $targetId, ?string $targetEmail, ?string $details, string $ip): void
    {
        $this->execute(
            'INSERT INTO admin_audit_logs (admin_id, action, target_user_id, target_email, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$adminId, $action, $targetId, $targetEmail, $details !== null ? mb_substr($details, 0, 255) : null, $ip]
        );
    }

    public function recent(int $limit = 20): array
    {
        return $this->fetchAll(
            'SELECT l.*, u.name AS admin_name FROM admin_audit_logs l
          LEFT JOIN users u ON u.id = l.admin_id
           ORDER BY l.created_at DESC, l.id DESC LIMIT ' . max(1, min(100, $limit))
        );
    }
}
