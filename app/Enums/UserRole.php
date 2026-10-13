<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Internal = 'internal';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Internal => __('Internal'),
            self::Client => __('Client'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'purple',
            self::Internal => 'blue',
            self::Client => 'zinc',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Client;
    }
}
