<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Analyst = 'analyst';
    case Viewer = 'viewer';

    public function level(): int
    {
        return match ($this) {
            self::Admin => 4,
            self::Manager => 3,
            self::Analyst => 2,
            self::Viewer => 1,
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->level() >= $minimum->level();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::Manager => 'Менеджер',
            self::Analyst => 'Аналитик',
            self::Viewer => 'Наблюдатель',
        };
    }
}
