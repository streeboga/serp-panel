<?php

declare(strict_types=1);

use App\Console\Commands\FinalizeStuckAuditsCommand;
use App\Models\SiteAudit;

covers(FinalizeStuckAuditsCommand::class);

test('прогон, застрявший без движения, закрывается', function () {
    $h = createFullStack();

    $stuck = SiteAudit::create([
        'project_id' => $h['project']->id,
        'scope' => 'site',
        'status' => 'running',
        'pages_total' => 10,
    ]);

    // Батч потерялся: обновлений нет уже час.
    $stuck->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    $this->artisan('audit:finalize-stuck')->assertSuccessful();

    expect($stuck->refresh()->status->value)->toBe('completed')
        // Про недобранные страницы прогон обязан сказать вслух.
        ->and($stuck->error)->toContain('10 из 10');
});

test('свежий прогон не трогаем', function () {
    $h = createFullStack();

    $fresh = SiteAudit::create([
        'project_id' => $h['project']->id,
        'scope' => 'site',
        'status' => 'running',
        'pages_total' => 10,
    ]);

    $this->artisan('audit:finalize-stuck')->assertSuccessful();

    expect($fresh->refresh()->status->value)->toBe('running');
});
