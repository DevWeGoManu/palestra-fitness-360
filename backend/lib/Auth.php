<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = Database::connection()->prepare(
        'SELECT id, full_name, email, role, status, email_verified_at, created_at
         FROM users
         WHERE id = ? AND status = ? AND email_verified_at IS NOT NULL
         LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id'], 'active']);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['error' => 'Autenticazione richiesta'], 401);
    }
    return $user;
}

function require_role(array $allowedRoles): array
{
    $user = require_user();
    if (!in_array($user['role'], $allowedRoles, true)) {
        json_response(['error' => 'Permesso negato'], 403);
    }
    return $user;
}

function can_manage(array $user): bool
{
    return in_array($user['role'], ['admin', 'istruttore'], true);
}

function can_edit_own_workouts(array $user): bool
{
    return in_array($user['role'], ['admin', 'istruttore', 'autonomo'], true);
}
