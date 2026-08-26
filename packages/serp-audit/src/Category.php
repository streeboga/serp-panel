<?php

declare(strict_types=1);

namespace SerpAudit;

/**
 * Категории проверок. Обычные строки, а не enum: свой пакет вправе завести
 * собственную категорию, и реестр её примет — иначе «подключить как драйвер»
 * работало бы только для тех типов, что мы предугадали заранее.
 */
final class Category
{
    public const TECHNICAL = 'technical';

    public const META = 'meta';

    public const CONTENT = 'content';

    public const LINKS = 'links';

    public const IMAGES = 'images';

    /** @var array<string, string> Человеческие названия встроенных категорий. */
    private const TITLES = [
        self::TECHNICAL => 'Технические данные',
        self::META => 'Мета-теги и разметка',
        self::CONTENT => 'Контент',
        self::LINKS => 'Ссылки',
        self::IMAGES => 'Изображения',
    ];

    public static function title(string $category): string
    {
        return self::TITLES[$category] ?? $category;
    }
}
