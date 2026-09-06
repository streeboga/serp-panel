<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\AuditResourceRepositoryInterface;
use App\Contracts\Repositories\PageAuditResultRepositoryInterface;
use App\Models\AuditResource;
use App\Models\SiteAudit;
use Generator;

/**
 * Выгрузки прогона в том разрезе, в каком их просят в закупках: список URL с кодами,
 * все мета-теги, битые ссылки, все находки построчно.
 *
 * Каждый метод отдаёт генератор: на тысячах страниц с находками массив в память
 * не помещается, а выгрузка нужна именно на больших сайтах.
 */
final readonly class AuditExportService
{
    public function __construct(
        private PageAuditResultRepositoryInterface $results,
        private AuditResourceRepositoryInterface $resources,
    ) {}

    /**
     * Полный список проверенных URL с кодами ответов.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function pages(SiteAudit $audit): Generator
    {
        foreach ($this->results->lazyForAudit($audit->id) as $result) {
            $metrics = $result->metrics ?? [];

            yield [
                'URL' => $result->url,
                'Код ответа' => $result->http_status,
                'Редиректов' => $metrics['redirects'] ?? null,
                'Кликов от главной' => $result->depth,
                'Входящих ссылок' => $result->inbound_links,
                'Оценка' => $result->score,
                'Ошибки' => $result->issues_critical,
                'Предупреждения' => $result->issues_warning,
                'Замечания' => $result->issues_notice,
                'Ответ, мс' => $result->response_time_ms,
                'Размер HTML' => $result->html_size,
                'Слов' => $metrics['words'] ?? null,
                'Текст к HTML, %' => $metrics['text_html_ratio'] ?? null,
                'Вода, %' => $metrics['water'] ?? null,
                'Читаемость' => $metrics['readability']['score'] ?? null,
                'Внутренних ссылок' => $metrics['links_internal'] ?? null,
                'Внешних ссылок' => $metrics['links_external'] ?? null,
                'Картинок' => $metrics['images_total'] ?? null,
                'Без alt' => $metrics['images_alt_missing'] ?? null,
                'CLS' => $metrics['browser']['cls'] ?? null,
                'LCP, мс' => $metrics['browser']['lcp'] ?? null,
                'Ошибок W3C' => $metrics['w3c']['errors'] ?? null,
                'Ошибка обхода' => $result->error,
            ];
        }
    }

    /**
     * Выгрузка всех Title, Description и заголовков.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function meta(SiteAudit $audit): Generator
    {
        foreach ($this->results->lazyForAudit($audit->id) as $result) {
            $metrics = $result->metrics ?? [];

            yield [
                'URL' => $result->url,
                'Title' => $metrics['title'] ?? null,
                'Длина Title' => $metrics['title_length'] ?? null,
                'Description' => $metrics['description'] ?? null,
                'Длина Description' => $metrics['description_length'] ?? null,
                'H1' => $metrics['h1'] ?? null,
                'H1, шт.' => $metrics['h1_count'] ?? null,
                'H2, шт.' => $metrics['h2_count'] ?? null,
                'H3, шт.' => $metrics['h3_count'] ?? null,
                'Заголовков всего' => $metrics['headings_total'] ?? null,
                'Canonical' => $metrics['canonical'] ?? null,
                'Meta robots' => $metrics['robots'] ?? null,
                'X-Robots-Tag' => $metrics['x_robots_tag'] ?? null,
                'lang' => $metrics['lang'] ?? null,
                'Schema.org' => implode(', ', $metrics['schema_types'] ?? []),
            ];
        }
    }

    /**
     * Битые ссылки и файлы: что не открывается и откуда на это ссылаются.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function broken(SiteAudit $audit): Generator
    {
        foreach ($this->resources->lazyForAudit($audit->id) as $resource) {
            if ($resource->checked_at === null) {
                continue;
            }

            if ($resource->status !== null && $resource->status < 400) {
                continue;
            }

            yield [
                'URL' => $resource->url,
                'Тип' => $resource->type === AuditResource::TYPE_IMAGE ? 'изображение' : 'ссылка',
                'Код ответа' => $resource->status,
                'Ссылающихся страниц' => $resource->reference_count,
                'Ошибка' => $resource->error,
            ];
        }
    }

    /**
     * Все находки построчно — «страницы с ошибками» из требований к отчёту.
     * Сначала находки уровня сайта, затем постраничные.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function findings(SiteAudit $audit): Generator
    {
        foreach ($audit->findings ?? [] as $finding) {
            yield $this->row('весь сайт', $finding);
        }

        foreach ($this->results->lazyForAudit($audit->id) as $result) {
            foreach ($result->findings ?? [] as $finding) {
                yield $this->row($result->url, $finding);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    private function row(string $url, array $finding): array
    {
        $value = $finding['value'] ?? null;

        return [
            'URL' => $url,
            'Важность' => match ($finding['severity'] ?? '') {
                'critical' => 'Ошибка',
                'warning' => 'Предупреждение',
                'notice' => 'Замечание',
                default => $finding['severity'] ?? '',
            },
            'Категория' => $finding['category'] ?? '',
            'Проверка' => $finding['check'] ?? '',
            'Код' => $finding['code'] ?? '',
            'Описание' => $finding['message'] ?? '',
            // Структурные значения разворачивать в CSV некуда — отдаём как есть,
            // чтобы строка оставалась одной строкой.
            'Значение' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
            'Ожидается' => is_array($finding['expected'] ?? null)
                ? json_encode($finding['expected'], JSON_UNESCAPED_UNICODE)
                : ($finding['expected'] ?? null),
        ];
    }

    /** @return array<string, string> Что вообще можно выгрузить. */
    public function datasets(): array
    {
        return [
            'pages' => 'Все проверенные URL с кодами ответов и метриками',
            'meta' => 'Title, Description и заголовки по каждой странице',
            'broken' => 'Битые ссылки и файлы',
            'findings' => 'Все находки построчно',
        ];
    }

    /** @return Generator<int, array<string, mixed>> */
    public function dataset(SiteAudit $audit, string $name): Generator
    {
        yield from match ($name) {
            'pages' => $this->pages($audit),
            'meta' => $this->meta($audit),
            'broken' => $this->broken($audit),
            'findings' => $this->findings($audit),
            default => throw new \InvalidArgumentException("Неизвестная выгрузка [{$name}]"),
        };
    }
}
