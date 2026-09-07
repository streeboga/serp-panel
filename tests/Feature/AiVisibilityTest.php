<?php

declare(strict_types=1);

use App\Services\AiVisibility\AnthropicClient;
use App\Services\AiVisibility\BrandLookup;
use Illuminate\Support\Facades\Http;

covers(AnthropicClient::class, BrandLookup::class);

it('без ключа честно отвечает, что проверка невозможна', function () {
    config(['services.anthropic.key' => null]);
    $h = createFullStack();

    $this->actingAs($h['user'])
        ->postJson('/api/v1/ai-visibility/lookup', ['brand' => 'Cordiant', 'domain' => 'cordiant.ru', 'prompts' => ['какие шины купить']], orgHeaders($h['org']))
        ->assertStatus(422)
        ->assertJsonPath('errors.0.title', 'ИИ-провайдер не настроен');
});

it('считает долю голоса и цитирования по ответам модели', function () {
    config(['services.anthropic.key' => 'test-key']);
    $h = createFullStack();

    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['content' => [['type' => 'text', 'text' => 'Популярны Cordiant и Nokian. Подробнее: https://www.cordiant.ru/catalog/ и https://nokiantyres.ru/']]])
        ->push(['content' => [['type' => 'text', 'text' => 'Я бы посмотрел Nokian — см. https://nokiantyres.ru/summer']]]),
    ]);

    $response = $this->actingAs($h['user'])->postJson('/api/v1/ai-visibility/lookup', [
        'brand' => 'Cordiant', 'domain' => 'cordiant.ru', 'competitors' => ['Nokian'],
        'prompts' => ['какие летние шины взять', 'лучшие шины для города'],
    ], orgHeaders($h['org']))->assertOk();

    $data = $response->json('data');

    expect($data['share_of_voice']['Cordiant'])->toBe(['mentions' => 1, 'share' => 33.3])
        ->and($data['share_of_voice']['Nokian']['mentions'])->toBe(2)
        ->and($data['our_citations'])->toBe(1)
        ->and(array_key_first($data['cited_hosts']))->toBe('nokiantyres.ru')
        ->and($data['unanswered'])->toBe(0);
});

it('не путает бренд с частью другого слова', function () {
    $lookup = new BrandLookup(new AnthropicClient);
    $m = new ReflectionMethod($lookup, 'mentions');

    expect($m->invoke($lookup, 'Бренд Cordiant хорош', 'Cordiant'))->toBeTrue()
        ->and($m->invoke($lookup, 'Слово Cordiantovka — не бренд', 'Cordiant'))->toBeFalse();
});
