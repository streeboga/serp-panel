# serp-audit

Проверки качества страницы: контракт, реестр и стандартный набор.

Каждая проверка — отдельный класс со своим кодом и категорией. Реестр собирает их
из всех установленных пакетов, поэтому новый набор подключается как драйвер:
поставили пакет — проверки появились в каталоге и в прогоне.

## Своя проверка

```php
use SerpAudit\Category;
use SerpAudit\Contracts\PageCheck;
use SerpAudit\Finding;
use SerpAudit\PageContext;
use SerpAudit\Severity;

final class AmpCheck implements PageCheck
{
    public function code(): string { return 'meta.amp.missing'; }

    public function category(): string { return Category::META; }

    public function title(): string { return 'AMP-версия страницы'; }

    public function run(PageContext $context): array
    {
        if ($context->firstValueAttr("//link[@rel='amphtml']", 'href') !== null) {
            return [];
        }

        return [new Finding($this, Severity::Notice, 'AMP-версия не объявлена')];
    }

    public function metrics(PageContext $context): array { return []; }
}
```

Категория — обычная строка: свой пакет вправе завести собственную, реестр примет.

Регистрация в сервис-провайдере пакета:

```php
public function boot(CheckRegistry $registry): void
{
    $registry->register(new AmpCheck);
}
```
