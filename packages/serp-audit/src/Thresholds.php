<?php

declare(strict_types=1);

namespace SerpAudit;

/**
 * Пороги срабатывания. Пакет не лезет в конфиг фреймворка — значения ему
 * передаёт приложение, а свои дефолты он держит на случай использования без него.
 */
final readonly class Thresholds
{
    private const DEFAULTS = [
        'title_min' => 10,
        'title_max' => 70,
        'description_min' => 50,
        'description_max' => 320,
        'h2_max' => 8,
        'response_time_ms' => 1500,
        'html_size_kb' => 300,
        'text_html_ratio_min' => 25.0,
        'water_max' => 60.0,
        'classic_nausea_max' => 7.0,
        'academic_nausea_max' => 30.0,
        'keyword_density_max' => 5.0,
        'words_min' => 300,
    ];

    /** @var array<string, int|float> */
    private array $values;

    /** @param array<string, int|float> $overrides */
    public function __construct(array $overrides = [])
    {
        $this->values = [...self::DEFAULTS, ...$overrides];
    }

    public function int(string $key): int
    {
        return (int) ($this->values[$key] ?? 0);
    }

    public function float(string $key): float
    {
        return (float) ($this->values[$key] ?? 0);
    }
}
