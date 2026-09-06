<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Разбор robots.txt: директивы Sitemap и правила Disallow для нашего user-agent.
 * Своя реализация вместо пакета — формат простой, а зависимость лишняя.
 */
final readonly class RobotsTxt
{
    /**
     * @param  array<int, string>  $sitemaps
     * @param  array<int, string>  $disallow
     * @param  array<int, string>  $allow
     * @param  array<int, string>  $deprecatedDirectives
     */
    private function __construct(
        public bool $found,
        public array $sitemaps,
        public array $disallow,
        public array $allow,
        public array $deprecatedDirectives,
    ) {}

    public static function missing(): self
    {
        return new self(false, [], [], [], []);
    }

    public static function parse(string $body, string $userAgent = '*'): self
    {
        $sitemaps = [];
        $disallow = [];
        $allow = [];
        $deprecated = [];
        $applies = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$directive, $value] = array_map(trim(...), explode(':', $line, 2));
            $directive = mb_strtolower($directive);

            match ($directive) {
                // Sitemap глобальна и не зависит от секции User-agent.
                'sitemap' => $sitemaps[] = $value,
                // Отменённые директивы: Host и Crawl-delay Яндекс перестал учитывать
                // в 2018-м, но обе продолжают жить в старых файлах.
                // Clean-param отсюда убрана: она действующая и Яндекс прямо просит
                // «заполняйте её максимально полно и поддерживайте актуальность»
                // (yandex.ru/support/webmaster/robot-workings/clean-param.html,
                // проверено 06.09.2026). Несколько строк Clean-param в одной секции
                // это норма, а не дубль.
                'host', 'crawl-delay' => $deprecated[] = $line,
                'user-agent' => $applies = ($value === '*' || mb_stripos($userAgent, $value) !== false),
                'disallow' => $applies && $value !== '' ? $disallow[] = $value : null,
                'allow' => $applies && $value !== '' ? $allow[] = $value : null,
                default => null,
            };
        }

        return new self(true, array_values(array_unique($sitemaps)), $disallow, $allow, $deprecated);
    }

    public function allows(string $path): bool
    {
        if (! $this->found) {
            return true;
        }

        $matches = static function (string $rule) use ($path): bool {
            $pattern = '~^'.str_replace('\*', '.*', preg_quote(rtrim($rule, '$'), '~')).(str_ends_with($rule, '$') ? '$' : '').'~';

            return preg_match($pattern, $path) === 1;
        };

        foreach ($this->allow as $rule) {
            if ($matches($rule)) {
                return true;
            }
        }

        foreach ($this->disallow as $rule) {
            if ($matches($rule)) {
                return false;
            }
        }

        return true;
    }
}
