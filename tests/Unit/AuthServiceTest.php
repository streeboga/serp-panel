<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Validation\ValidationException;

covers(AuthService::class);

test('register creates user organization and token', function () {
    $service = app(AuthService::class);

    $result = $service->register('Test', 'test@example.com', 'password', 'My Org');

    expect($result)->toHaveKeys(['user', 'organization', 'token']);
    expect($result['user'])->toBeInstanceOf(User::class);
    expect($result['user']->email)->toBe('test@example.com');
    expect($result['organization']->name)->toBe('My Org');
    expect($result['token'])->toBeString();

    $this->assertDatabaseHas('organization_user', [
        'user_id' => $result['user']->id,
        'organization_id' => $result['organization']->id,
        'role' => 'admin',
    ]);
});

test('register with locale and theme', function () {
    $service = app(AuthService::class);

    $result = $service->register('Test', 'test@example.com', 'password', 'My Org', 'ru', 'dark');

    expect($result['user']->locale)->toBe('ru');
    expect($result['user']->theme)->toBe('dark');
});

test('login returns user and token', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $service = app(AuthService::class);
    $result = $service->login('test@example.com', 'password');

    expect($result)->toHaveKeys(['user', 'token']);
    expect($result['user']->email)->toBe('test@example.com');
});

test('login throws on wrong password', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $service = app(AuthService::class);
    $service->login('test@example.com', 'wrong');
})->throws(ValidationException::class);

test('login throws on non-existent user', function () {
    $service = app(AuthService::class);
    $service->login('nobody@example.com', 'password');
})->throws(ValidationException::class);
