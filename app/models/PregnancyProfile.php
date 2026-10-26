<?php

declare(strict_types=1);

namespace App\Models;

final class PregnancyProfile extends Model
{
    public function findByUser(int $userId): ?array
    {
        return $this->fetch('SELECT * FROM pregnancy_profiles WHERE user_id = ?', [$userId]);
    }

    public function save(int $userId, array $data): void
    {
        $this->execute(
            'INSERT INTO pregnancy_profiles
                (user_id, nickname, estimated_due_date, tracking_start_date, notes, companion_name, companion_contact, created_at, updated_at)
             VALUES (:user_id, :nickname, :edd, :tsd, :notes, :cname, :ccontact, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                nickname = VALUES(nickname),
                estimated_due_date = VALUES(estimated_due_date),
                tracking_start_date = VALUES(tracking_start_date),
                notes = VALUES(notes),
                companion_name = VALUES(companion_name),
                companion_contact = VALUES(companion_contact),
                updated_at = UTC_TIMESTAMP()',
            [
                ':user_id'  => $userId,
                ':nickname' => $data['nickname'],
                ':edd'      => $data['estimated_due_date'],
                ':tsd'      => $data['tracking_start_date'],
                ':notes'    => $data['notes'],
                ':cname'    => $data['companion_name'],
                ':ccontact' => $data['companion_contact'],
            ]
        );
    }
}
