<?php

function clean_string(mixed $value, int $maxLength = 255): string
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
}

function clean_text(mixed $value, int $maxLength = 2000): string
{
    $value = trim((string) $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
}

function valid_role(string $role): bool
{
    return in_array($role, ['admin', 'istruttore', 'atleta', 'autonomo'], true);
}

function valid_status(string $status): bool
{
    return in_array($status, ['pending', 'active', 'disabled'], true);
}

function valid_muscle_group(string $group): bool
{
    return in_array($group, ['petto', 'schiena', 'gambe', 'spalle', 'braccia', 'addome', 'cardio'], true);
}
