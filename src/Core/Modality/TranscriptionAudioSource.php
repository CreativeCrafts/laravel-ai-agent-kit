<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Security\MediaInputSecurityConfig;
use CreativeCrafts\LaravelAiAgentKit\Security\MediaSourceSafeMetadata;
use CreativeCrafts\LaravelAiAgentKit\Security\SafeHttpUrlValidator;
use CreativeCrafts\LaravelAiAgentKit\Security\SafeLocalPathReferenceValidator;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final readonly class TranscriptionAudioSource
{
    private function __construct(
        private TranscriptionAudioSourceKind $kind,
        private string|UploadedFile $payload,
        private ?string $mimeType = null,
        private ?string $disk = null,
    ) {
        if ($this->mimeType !== null && trim($this->mimeType) === '') {
            throw new InvalidArgumentException('Transcription audio source MIME type must be null or a non-empty string.');
        }

        if ($this->disk !== null && trim($this->disk) === '') {
            throw new InvalidArgumentException('Transcription audio source disk must be null or a non-empty string.');
        }
    }

    public static function fromBase64(string $base64, ?string $mimeType = null): self
    {
        if ($base64 === '') {
            throw new InvalidArgumentException('Transcription base64 audio source requires a non-empty payload.');
        }

        return new self(TranscriptionAudioSourceKind::Base64, $base64, $mimeType);
    }

    public static function fromPath(string $path, ?string $mimeType = null): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('Transcription path audio source requires a non-empty path.');
        }

        SafeLocalPathReferenceValidator::assertSafeReference($path, 'Transcription path audio source');

        return new self(TranscriptionAudioSourceKind::Path, $path, $mimeType);
    }

    public static function fromStorage(string $path, ?string $disk = null, ?string $mimeType = null): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('Transcription storage audio source requires a non-empty path.');
        }

        SafeLocalPathReferenceValidator::assertSafeReference($path, 'Transcription storage audio source');

        return new self(TranscriptionAudioSourceKind::Storage, $path, $mimeType, $disk);
    }

    public static function fromUpload(UploadedFile $file, ?string $mimeType = null): self
    {
        return new self(TranscriptionAudioSourceKind::Upload, $file, $mimeType);
    }

    public static function fromUrl(string $url, ?string $mimeType = null): self
    {
        if ($url === '') {
            throw new InvalidArgumentException('Transcription URL audio source requires a non-empty URL.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Transcription URL audio source requires a valid absolute URL.');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Transcription URL audio source only supports HTTP(S) URLs.');
        }

        SafeHttpUrlValidator::assertPublicHttpUrl(
            $url,
            'Transcription URL audio source',
            MediaInputSecurityConfig::urlAllowedHosts(),
        );

        return new self(TranscriptionAudioSourceKind::Url, $url, $mimeType);
    }

    public function kind(): TranscriptionAudioSourceKind
    {
        return $this->kind;
    }

    public function payload(): string|UploadedFile
    {
        return $this->payload;
    }

    public function mimeType(): ?string
    {
        return $this->mimeType;
    }

    public function disk(): ?string
    {
        return $this->disk;
    }

    /**
     * @return array<string, mixed>
     */
    public function safeMetadata(): array
    {
        $metadata = [
            'kind' => $this->kind->value,
            'mime_type' => $this->mimeType,
            'disk' => $this->disk,
        ];

        if ($this->kind === TranscriptionAudioSourceKind::Base64 && is_string($this->payload)) {
            $metadata['payload_length'] = strlen($this->payload);
        }

        if (in_array($this->kind, [TranscriptionAudioSourceKind::Path, TranscriptionAudioSourceKind::Storage, TranscriptionAudioSourceKind::Url], true) && is_string($this->payload)) {
            $metadata = [
                ...$metadata,
                ...MediaSourceSafeMetadata::referenceFields(
                    $this->payload,
                    $this->kind === TranscriptionAudioSourceKind::Url,
                ),
            ];
        }

        if ($this->kind === TranscriptionAudioSourceKind::Upload && $this->payload instanceof UploadedFile) {
            $metadata['client_original_name'] = $this->payload->getClientOriginalName();
            $metadata['client_mime_type'] = $this->payload->getClientMimeType();
        }

        return array_filter(
            $metadata,
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
