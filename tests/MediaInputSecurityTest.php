<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\EvaluationImageInput;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\DnsResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSource;
use CreativeCrafts\LaravelAiAgentKit\Security\MediaHostMatchMode;
use CreativeCrafts\LaravelAiAgentKit\Security\SafeHttpUrlValidator;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    config()->set('ai-agent-kit.media_input.require_https', false);
    config()->set('ai-agent-kit.media_input.host_match', 'exact_and_subdomains');
    config()->set('ai-agent-kit.media_input.include_diagnostic_names', false);
    config()->set('ai-agent-kit.media_input.url_allowed_hosts', []);
});

it('deterministically accepts public DNS addresses', function (array $addresses): void {
    SafeHttpUrlValidator::assertPublicHttpUrl(
        'https://media.example.test/file',
        'Media test',
        dnsResolver: new MediaTestDnsResolver($addresses),
    );

    expect(true)->toBeTrue();
})->with([
    'public A' => [['93.184.216.34']],
    'public AAAA' => [['2001:4860:4860::8888']],
]);

it('deterministically rejects any private DNS address', function (array $addresses): void {
    SafeHttpUrlValidator::assertPublicHttpUrl(
        'https://media.example.test/file',
        'Media test',
        dnsResolver: new MediaTestDnsResolver($addresses),
    );
})->throws(InvalidArgumentException::class, 'private or reserved')->with([
    'private A' => [['10.0.0.10']],
    'private AAAA' => [['fd00::10']],
    'mixed public and private' => [['93.184.216.34', '192.168.1.10']],
]);

it('preserves compatibility behavior when DNS resolution returns no addresses', function (): void {
    SafeHttpUrlValidator::assertPublicHttpUrl(
        'https://unresolved.example.test/file',
        'Media test',
        dnsResolver: new MediaTestDnsResolver([]),
    );

    expect(true)->toBeTrue();
});

it('can require HTTPS for remote media', function (): void {
    config()->set('ai-agent-kit.media_input.require_https', true);

    EvaluationImageInput::fromUrl('http://example.invalid/question.jpg');
})->throws(InvalidArgumentException::class, 'requires an HTTPS URL');

it('supports exact-only host matching without changing the compatibility default', function (): void {
    SafeHttpUrlValidator::assertPublicHttpUrl(
        'https://cdn.example.test/file',
        'Media test',
        ['example.test'],
        hostMatchMode: MediaHostMatchMode::ExactAndSubdomains,
        dnsResolver: new MediaTestDnsResolver([]),
    );

    expect(fn (): mixed => SafeHttpUrlValidator::assertPublicHttpUrl(
        'https://cdn.example.test/file',
        'Media test',
        ['example.test'],
        hostMatchMode: MediaHostMatchMode::ExactOnly,
        dnsResolver: new MediaTestDnsResolver([]),
    ))->toThrow(InvalidArgumentException::class, 'allowlist');
});

it('omits user-controlled media names by default', function (): void {
    $storage = TranscriptionAudioSource::fromStorage(
        'tenant-7/candidate-jane@example.test.mp3',
        'private-audio',
        'audio/mpeg',
    )->safeMetadata();
    $upload = EvaluationImageInput::fromUpload(
        UploadedFile::fake()->create('candidate-jane@example.test.png', 12, 'image/png'),
    )->safeMetadata();

    expect($storage)
        ->not->toHaveKey('reference_basename')
        ->and($upload)->not->toHaveKey('client_original_name')
        ->and($upload)->toHaveKeys(['mime_type', 'byte_length', 'reference_fingerprint'])
        ->and(json_encode([$storage, $upload]))
        ->not->toContain('candidate-jane@example.test');
});

it('exposes diagnostic media names only when explicitly enabled', function (): void {
    config()->set('ai-agent-kit.media_input.include_diagnostic_names', true);

    $storage = TranscriptionAudioSource::fromStorage('tenant-7/audio.mp3')->safeMetadata();
    $upload = EvaluationImageInput::fromUpload(
        UploadedFile::fake()->create('candidate.png', 12, 'image/png'),
    )->safeMetadata();

    expect($storage['reference_basename'])->toBe('audio.mp3')
        ->and($upload['client_original_name'])->toBe('candidate.png');
});

final readonly class MediaTestDnsResolver implements DnsResolver
{
    /**
     * @param list<string> $addresses
     */
    public function __construct(private array $addresses)
    {
    }

    /**
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        return $this->addresses;
    }
}
