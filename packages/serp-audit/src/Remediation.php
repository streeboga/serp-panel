<?php

declare(strict_types=1);

namespace SerpAudit;

/**
 * «Как исправить» — по одной фразе на код находки.
 *
 * Идея из open-seo: у каждой строки выгрузки есть колонка How To Fix, и отчёт
 * читается как список задач, а не как список претензий. Текст подбирается
 * при выводе, в базе не хранится — менять формулировки можно без миграций.
 * Неизвестный код отдаёт null, и это нормально: лучше без подсказки, чем с
 * общей отпиской.
 */
final class Remediation
{
    /** @var array<string, string> */
    private const FIXES = [
        // Технические
        'http.status.server_error' => 'Посмотрите лог сервера за это время и почините ошибку 5xx — страница сейчас недоступна и роботу, и людям.',
        'http.status.not_available' => 'Верните страницу или отдайте 301 на замену; если её больше нет — 410.',
        'http.status.unexpected' => 'Приведите код ответа к 200 для рабочих страниц.',
        'http.status.content_type' => 'Отдавайте text/html с charset в заголовке Content-Type.',
        'http.redirect.chain' => 'Сократите цепочку до одного 301: ссылки должны вести сразу на конечный адрес.',
        'http.redirect.single' => 'Обновите ссылки на конечный адрес, чтобы не тратить обход на редирект.',
        'http.payload.slow' => 'Ускорьте ответ сервера: кеш на уровне приложения, лёгкие запросы к базе, CDN.',
        'http.payload.heavy' => 'Уменьшите HTML: вынесите инлайн-стили и скрипты, уберите лишнюю разметку.',
        'http.security_headers.clickjacking' => 'Добавьте заголовок X-Frame-Options: SAMEORIGIN или frame-ancestors в CSP.',
        'http.security_headers.strict_transport_security' => 'Включите HSTS: Strict-Transport-Security: max-age=31536000; includeSubDomains.',
        'http.security_headers.x_content_type_options' => 'Добавьте X-Content-Type-Options: nosniff.',
        'http.security_headers.content_security_policy' => 'Задайте Content-Security-Policy хотя бы в режиме report-only и сужайте постепенно.',
        'http.security_headers.referrer_policy' => 'Добавьте Referrer-Policy: strict-origin-when-cross-origin.',
        'http.security_headers.permissions_policy' => 'Добавьте Permissions-Policy и отключите неиспользуемые API браузера.',
        'http.analytics.missing' => 'Установите счётчик Яндекс.Метрики (и при необходимости GA4) на все страницы.',
        'http.technology.version_disclosed' => 'Уберите версии из заголовков Server и X-Powered-By в настройках веб-сервера и PHP (expose_php=Off).',
        'http.technology.generator_version' => 'Удалите meta generator или оставьте без номера версии.',
        'http.assets.blocking_scripts' => 'Перенесите скрипты из <head> в конец страницы или добавьте defer/async.',
        'http.assets.many_scripts' => 'Объедините и минифицируйте скрипты, уберите неиспользуемые.',
        'http.assets.many_styles' => 'Объедините таблицы стилей, критический CSS — инлайном.',
        'http.caching.missing' => 'Отдавайте Cache-Control для страниц и долгий max-age для статики.',
        'http.caching.no_validator' => 'Добавьте ETag или Last-Modified, чтобы повторные запросы отвечали 304.',
        'http.caching.html_too_long' => 'Сократите max-age для HTML: длинный кеш задерживает обновления контента.',
        'http.indexing_header.noindex' => 'Уберите noindex из X-Robots-Tag, если страница должна быть в поиске.',
        'http.indexing_header.nofollow' => 'Уберите nofollow из X-Robots-Tag, чтобы робот шёл по ссылкам.',
        'http.indexing_header.none' => 'Уберите директиву none из X-Robots-Tag.',
        'http.mixed_content.active' => 'Переведите скрипты, стили и фреймы на https — браузер их блокирует.',
        'http.mixed_content.passive' => 'Переведите картинки и медиа на https.',
        'http.dom.too_large' => 'Упростите разметку: уберите вложенные обёртки, подгружайте длинные списки по частям.',
        'http.forms.on_http' => 'Переведите страницу на HTTPS и настройте редирект с http.',
        'http.forms.insecure_action' => 'Замените action формы на https-адрес.',

        // Мета
        'meta.title.missing' => 'Добавьте <title> с главным запросом страницы, 30–70 знаков.',
        'meta.title.short' => 'Расширьте title до 30–70 знаков: запрос, уточнение, бренд.',
        'meta.title.long' => 'Сократите title до 70 знаков — остальное выдача обрежет.',
        'meta.description.missing' => 'Добавьте meta description: 120–160 знаков, с запросом и призывом.',
        'meta.description.short' => 'Расширьте description до 120–160 знаков.',
        'meta.description.long' => 'Сократите description — в выдаче поместится около 160 знаков.',
        'meta.description.chars' => 'Уберите из description спецсимволы и переводы строк.',
        'meta.headings.skip' => 'Выстройте заголовки по порядку: h1 → h2 → h3, без пропусков уровней.',
        'meta.indexing.noindex' => 'Уберите noindex из meta robots, если страница нужна в поиске.',
        'meta.indexing.nofollow' => 'Уберите nofollow из meta robots.',
        'meta.indexing.canonical_missing' => 'Добавьте <link rel="canonical"> с абсолютным адресом самой страницы.',
        'meta.indexing.canonical_mismatch' => 'Проверьте, что canonical указывает на нужную страницу; для дублей это нормально, для основных — ошибка.',
        'meta.document.structure' => 'Исправьте структуру: один <html>, один <head>, один <body>.',
        'meta.document.lang_missing' => 'Укажите язык: <html lang="ru">.',
        'meta.document.charset_missing' => 'Добавьте <meta charset="utf-8"> первым в <head>.',
        'meta.document.viewport_missing' => 'Добавьте <meta name="viewport" content="width=device-width, initial-scale=1">.',
        'meta.document.style_in_body' => 'Перенесите <style> в <head>.',
        'meta.document.div_in_head' => 'Уберите из <head> элементы, которым место в <body>.',
        'meta.social.opengraph_missing' => 'Добавьте og:title, og:description, og:image и og:url.',
        'meta.social.opengraph_incomplete' => 'Дополните Open Graph недостающими полями.',
        'meta.social.schema_missing' => 'Добавьте разметку Schema.org (JSON-LD) под тип страницы.',
        'meta.schema.incomplete' => 'Заполните обязательные поля Schema.org для указанного типа.',
        'meta.legacy.deprecated_tags' => 'Замените устаревшие теги (font, center, marquee) на CSS.',
        'meta.legacy.flash' => 'Удалите Flash — браузеры его не поддерживают.',
        'meta.legacy.iframe' => 'По возможности замените iframe на встроенный контент.',
        'meta.language.mismatch' => 'Приведите lang в соответствие языку текста.',
        'meta.url.too_long' => 'Сократите адрес: короткий путь без лишних сегментов.',
        'meta.url.too_deep' => 'Уменьшите вложенность адреса.',
        'meta.url.underscore' => 'Используйте дефисы вместо подчёркиваний в адресе.',
        'meta.url.uppercase' => 'Приведите адрес к нижнему регистру и настройте редирект.',
        'meta.url.non_latin' => 'По возможности используйте латиницу в адресах.',
        'meta.url.extension' => 'Уберите расширения файлов (.php, .html) из адресов.',
        'meta.duplicates.multiple_title' => 'Оставьте один <title>.',
        'meta.duplicates.multiple_description' => 'Оставьте один meta description.',
        'meta.duplicates.multiple_canonical' => 'Оставьте одну каноническую ссылку.',
        'meta.duplicates.canonical_relative' => 'Сделайте canonical абсолютным: с протоколом и доменом.',
        'meta.duplicates.metas_in_body' => 'Перенесите meta, title и canonical в <head>.',
        'meta.hreflang.x_default_missing' => 'Добавьте hreflang="x-default" на версию по умолчанию.',
        'meta.hreflang.self_missing' => 'Добавьте в набор hreflang ссылку на саму страницу.',
        'meta.hreflang.invalid_lang' => 'Исправьте коды языков на ISO 639-1 (ru, en-GB).',
        'meta.snippet.nosnippet' => 'Уберите nosnippet, если сниппет в выдаче нужен.',
        'meta.snippet.max_snippet_zero' => 'Уберите max-snippet:0 или поставьте разумный лимит.',
        'meta.snippet.noimageindex' => 'Уберите noimageindex, если картинки должны находиться в поиске.',

        // Контент
        'content.text.few_words' => 'Дополните страницу содержательным текстом под её задачу.',
        'content.text.html_ratio' => 'Уменьшите объём разметки относительно текста.',
        'content.water.high' => 'Уберите вводные слова и общие фразы — оставьте суть.',
        'content.nausea.classic' => 'Разбавьте повторяющиеся слова синонимами.',
        'content.nausea.academic' => 'Снизьте повторы: текст читается как перечисление одного и того же.',
        'content.nausea.density' => 'Уменьшите плотность ключевого слова — переспам вредит.',
        'content.readability.hard' => 'Упростите текст: короче предложения, проще слова.',
        'content.readability.very_hard' => 'Перепишите текст проще: он не читается.',
        'content.readability.long_sentences' => 'Разбейте длинные предложения.',
        'content.relevance.title' => 'Включите целевой запрос в title.',
        'content.relevance.text' => 'Раскройте целевой запрос в тексте страницы.',

        // Ссылки
        'links.anchor.empty' => 'Дайте ссылкам осмысленный текст или aria-label.',
        'links.anchor.insecure' => 'Добавьте rel="noopener" ссылкам с target="_blank".',
        'links.external.dofollow' => 'Решите, каким внешним ссылкам передавать вес; остальным — rel="nofollow".',
        'links.volume.deadend' => 'Добавьте на страницу навигацию или ссылки на связанные материалы.',
        'links.volume.too_many' => 'Сократите число ссылок: оставьте важные для пользователя.',
        'links.volume.localhost' => 'Замените адреса localhost на боевые.',

        // Изображения
        'images.alt.missing' => 'Добавьте alt каждому изображению; декоративным — пустой alt="".',
        'images.alt.empty' => 'Проверьте, что пустой alt стоит только у декоративных картинок.',
        'images.source.no_dimensions' => 'Задайте width и height картинкам — иначе раскладка прыгает.',
        'images.source.external' => 'Разместите картинки на своём домене или CDN.',
        'images.delivery.legacy_format' => 'Отдавайте WebP/AVIF с запасным JPEG через <picture>.',
        'images.delivery.no_lazy' => 'Добавьте loading="lazy" картинкам ниже первого экрана.',
        'images.delivery.lazy_first' => 'Уберите lazy у первой картинки экрана — она должна грузиться сразу.',
        'images.attributes.long_alt' => 'Сократите alt до сути — до 100 знаков.',
        'images.attributes.picture_without_img' => 'Добавьте <img> внутрь <picture> как запасной вариант.',

        // Доступность
        'a11y.landmarks.main_missing' => 'Оберните основное содержимое в <main>.',
        'a11y.landmarks.nav_unnamed' => 'Дайте каждому <nav> aria-label, если их несколько.',
        'a11y.skip_link.missing' => 'Добавьте ссылку «Перейти к содержимому» первой в <body>.',
        'a11y.duplicate_id.found' => 'Сделайте id уникальными на странице.',
        'a11y.form_label.missing' => 'Свяжите каждое поле с <label> или дайте aria-label.',
        'a11y.table_header.missing' => 'Добавьте <th> в таблицы с данными.',
        'a11y.accessible_name.button_nameless' => 'Дайте кнопкам текст или aria-label.',
        'a11y.accessible_name.link_nameless' => 'Дайте ссылкам текст или aria-label.',

        // Юридическое
        'legal.consent.missing' => 'Добавьте согласие на обработку персональных данных к формам.',
        'legal.policy_link.missing' => 'Добавьте ссылку на политику обработки персональных данных.',

        // Уровень сайта
        'site.robots.missing' => 'Создайте robots.txt с указанием Sitemap и нужных Disallow.',
        'site.robots.blocks_root' => 'Уберите Disallow: / — сайт закрыт от индексации целиком.',
        'site.robots.deprecated' => 'Уберите устаревшие директивы (Host, Crawl-delay) из robots.txt.',
        'site.robots.no_sitemap' => 'Добавьте строку Sitemap: с адресом карты в robots.txt.',
        'site.sitemap.missing' => 'Создайте sitemap.xml и укажите его в robots.txt.',
        'site.sitemap.broken' => 'Почините карту сайта: валидный XML, доступный по адресу.',
        'site.sitemap.duplicates' => 'Уберите повторяющиеся адреса из карты.',
        'site.sitemap.no_lastmod' => 'Добавьте lastmod с реальной датой изменения страниц.',
        'site.sitemap.future_lastmod' => 'Исправьте lastmod: даты в будущем робот игнорирует.',
        'site.sitemap.blocked_by_robots' => 'Согласуйте карту и robots.txt: уберите из карты закрытые адреса или откройте их.',
        'site.sitemap.noindex_pages' => 'Уберите из карты страницы с noindex — или снимите noindex.',
        'site.sitemap.non_canonical' => 'Оставьте в карте только канонические адреса.',
        'site.ssl.missing' => 'Установите SSL-сертификат и включите HTTPS.',
        'site.ssl.expired' => 'Обновите сертификат — он истёк.',
        'site.ssl.expiring' => 'Продлите сертификат заранее или включите автообновление.',
        'site.ssl.unreachable' => 'Проверьте, что сайт отвечает по 443 порту.',
        'site.not_found' => 'Сделайте страницу 404 с кодом 404 и навигацией.',
        'site.redirect.https' => 'Настройте 301 с http на https.',
        'site.redirect.slash' => 'Выберите один вариант (со слэшем или без) и редиректьте второй.',
        'site.redirect.index' => 'Настройте 301 с /index.php и /index.html на корень.',
        'site.compression.missing' => 'Включите gzip или brotli на сервере.',
        'site.favicon.missing' => 'Добавьте favicon и укажите его через <link rel="icon">.',
        'site.resources.broken' => 'Почините или удалите битые ссылки и файлы.',
        'site.resources.heavy_images' => 'Сожмите тяжёлые изображения, отдавайте нужный размер.',
        'site.duplicate.title' => 'Сделайте title уникальным на каждой странице.',
        'site.duplicate.description' => 'Сделайте description уникальным на каждой странице.',
        'site.near_duplicate.title' => 'Различайте title похожих страниц по сути, а не одним словом.',
        'site.near_duplicate.description' => 'Различайте description похожих страниц.',
        'site.params.duplicates' => 'Задайте canonical для адресов с параметрами или закройте их в robots.txt.',
        'site.anchors.spam' => 'Разнообразьте анкоры внутренних ссылок.',
        'site.structure.orphans' => 'Поставьте внутренние ссылки на страницы-сироты.',
        'site.structure.unreachable' => 'Свяжите изолированные разделы с остальным сайтом.',
        'site.structure.too_deep' => 'Поднимите важные страницы ближе к главной: до трёх кликов.',

        // Браузер, валидатор, поведение, панели
        'browser.cls' => 'Задайте размеры картинкам и блокам, не вставляйте контент над уже отрисованным.',
        'browser.lcp' => 'Ускорьте главный элемент экрана: приоритетная загрузка, меньший вес, без lazy.',
        'browser.contrast' => 'Повысьте контраст текста и фона до 4.5:1.',
        'browser.small_text' => 'Увеличьте шрифт до 16px на мобильных.',
        'browser.touch_targets' => 'Сделайте кликабельные элементы не меньше 44×44px с отступами.',
        'w3c.validation.errors' => 'Исправьте ошибки разметки по списку валидатора.',
        'behaviour.bounce' => 'Разберите страницы с высокими отказами: соответствие запросу, скорость, первый экран.',
        'search.webmaster.problems' => 'Откройте раздел «Диагностика» в Вебмастере и устраните перечисленное.',
        'search.console.striking_distance' => 'Усильте страницы под эти запросы: заголовки, текст, внутренние ссылки.',
    ];

    public static function for(string $code): ?string
    {
        return self::FIXES[$code] ?? null;
    }

    /**
     * Дописывает подсказку к каждой находке, у которой она есть.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<int, array<string, mixed>>
     */
    public static function attach(array $findings): array
    {
        foreach ($findings as &$finding) {
            $fix = self::for((string) ($finding['code'] ?? ''));

            if ($fix !== null) {
                $finding['fix'] = $fix;
            }
        }

        return $findings;
    }
}
