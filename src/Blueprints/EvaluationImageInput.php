<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Security\MediaSourceSafeMetadata;
use CreativeCrafts\LaravelAiAgentKit\Security\SafeHttpUrlValidator;
use CreativeCrafts\LaravelAiAgentKit\Security\SafeLocalPathReferenceValidator;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final readonly class EvaluationImageInput
{
    private function __construct(
        private EvaluationImageInputKind $kind,
        private string|UploadedFile $payload,
        private ?string $mimeType = null,
        private ?string $disk = null,
    ) {
        if ($this->mimeType !== null && trim($this->mimeType) === '') {
            throw new InvalidArgumentException('Evaluation image input MIME type must be null or a non-empty string.');
        }

        if ($this->disk !== null && trim($this->disk) === '') {
            throw new InvalidArgumentException('Evaluation image input disk must be null or a non-empty string.');
        }
    }

    public static function fromUrl(string $url): self
    {
        if ($url === '') {
            throw new InvalidArgumentException('Evaluation image URL input requires a non-empty URL.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Evaluation image URL input requires a valid absolute URL.');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Evaluation image URL input only supports HTTP(S) URLs.');
        }

        SafeHttpUrlValidator::assertPublicHttpUrl($url, 'Evaluation image URL input');

        return new self(EvaluationImageInputKind::Url, $url);
    }

    public static function fromBase64(string $base64, ?string $mimeType = null): self
    {
        if ($base64 === '') {
            throw new InvalidArgumentException('Evaluation image base64 input requires a non-empty payload.');
        }

        return new self(EvaluationImageInputKind::Base64, $base64, $mimeType);
    }

    public static function fromPath(string $path, ?string $mimeType = null): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('Evaluation image path input requires a non-empty path.');
        }

        SafeLocalPathReferenceValidator::assertSafeReference($path, 'Evaluation image path input');

        return new self(EvaluationImageInputKind::Path, $path, $mimeType);
    }

    public static function fromStorage(string $path, ?string $disk = null): self
    {
        if ($path === '') {
            throw new InvalidArgumentException('Evaluation image storage input requires a non-empty path.');
        }

        SafeLocalPathReferenceValidator::assertSafeReference($path, 'Evaluation image storage input');

        return new self(EvaluationImageInputKind::Storage, $path, disk: $disk);
    }

    public static function fromUpload(UploadedFile $file, ?string $mimeType = null): self
    {
        return new self(EvaluationImageInputKind::Upload, $file, $mimeType);
    }

    public function kind(): EvaluationImageInputKind
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

        if ($this->kind === EvaluationImageInputKind::Base64 && is_string($this->payload)) {
            $metadata['payload_length'] = strlen($this->payload);
        }

        if (in_array($this->kind, [EvaluationImageInputKind::Url, EvaluationImageInputKind::Path, EvaluationImageInputKind::Storage], true) && is_string($this->payload)) {
            $metadata = [
                ...$metadata,
                ...MediaSourceSafeMetadata::referenceFields(
                    $this->payload,
                    $this->kind === EvaluationImageInputKind::Url,
                ),
            ];
        }

        if ($this->kind === EvaluationImageInputKind::Upload && $this->payload instanceof UploadedFile) {
            $metadata['client_original_name'] = $this->payload->getClientOriginalName();
            $metadata['client_mime_type'] = $this->payload->getClientMimeType();
        }

        return array_filter(
            $metadata,
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
