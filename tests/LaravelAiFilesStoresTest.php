<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiFilesService;
use CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiStoresService;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Files;
use Laravel\Ai\Stores;

beforeEach(function (): void {
    app()->register(AiServiceProvider::class);

    /** @var array<string, mixed> $ai */
    $ai = require __DIR__.'/../vendor/laravel/ai/config/ai.php';
    Config::set('ai', $ai);
    Config::set('ai.default', 'openai');
    Config::set('ai.providers', [
        'openai' => [
            'driver' => 'openai',
            'key' => 'test-key-for-ci',
        ],
    ]);
});

it('stores and retrieves a file through the package files service', function (): void {
    Files::fake();

    /** @var LaravelAiFilesService $files */
    $files = app(LaravelAiFilesService::class);

    $stored = $files->put('hello world', mimeType: 'text/plain', provider: 'openai');
    expect($stored->id)->not->toBe('');

    $contents = $files->get($stored->id, 'openai');
    expect($contents->id)->toBe($stored->id)
        ->and($contents->content)->not->toBeNull();
});

it('creates a store and adds a document reference through the package stores service', function (): void {
    Stores::fake();

    /** @var LaravelAiFilesService $files */
    $files = app(LaravelAiFilesService::class);
    /** @var LaravelAiStoresService $stores */
    $stores = app(LaravelAiStoresService::class);

    $stored = $files->put('chunk for store', mimeType: 'text/plain', provider: 'openai');

    $created = $stores->create('support-docs', provider: 'openai');
    expect($created->id)->not->toBe('')
        ->and($created->ready)->toBeTrue();

    $added = $stores->addToStore($created->id, $stored->id, provider: 'openai');
    expect($added->documentId)->not->toBe('');

    $refreshed = $stores->refreshStore($created->id, 'openai');
    expect($refreshed->id)->toBe($created->id);
});

it('uses configured default providers when null is passed', function (): void {
    Files::fake();
    Stores::fake();

    config()->set('ai-agent-kit.laravel_ai_files.default_provider', 'openai');
    config()->set('ai-agent-kit.laravel_ai_stores.default_provider', 'openai');

    /** @var LaravelAiFilesService $files */
    $files = app(LaravelAiFilesService::class);
    /** @var LaravelAiStoresService $stores */
    $stores = app(LaravelAiStoresService::class);

    $stored = $files->put('defaults test');
    $created = $stores->create('defaults-store');
    $stores->addToStore($created->id, $stored->id);

    expect($stored->id)->not->toBe('');
});
