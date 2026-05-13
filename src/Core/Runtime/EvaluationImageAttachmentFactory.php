<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\EvaluationImageInput;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\EvaluationImageInputKind;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\UnsupportedEvaluationImageInputException;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;
use Laravel\Ai\ObjectSchema;

final readonly class EvaluationImageAttachmentFactory
{
    public function make(EvaluationImageInput $input): File
    {
        $payload = $input->payload();

        return match ($input->kind()) {
            EvaluationImageInputKind::Url => Image::fromUrl((string)$payload),
            EvaluationImageInputKind::Base64 => Image::fromBase64((string)$payload, $input->mimeType()),
            EvaluationImageInputKind::Path => Image::fromPath((string)$payload, $input->mimeType()),
            EvaluationImageInputKind::Storage => Image::fromStorage((string)$payload, $input->disk()),
            EvaluationImageInputKind::Upload => Image::fromUpload($this->uploadedFilePayload($input, $payload), $input->mimeType()),
        };
    }

    public function executionSchema(object|string $schema): Closure|ObjectSchema|string
    {
        if ($schema instanceof Closure || $schema instanceof ObjectSchema || is_string($schema)) {
            return $schema;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Audio-image structured evaluation schema object [%s] is not supported. Use a Closure, Laravel AI ObjectSchema, or class-string schema.',
                $schema::class,
            ),
        );
    }

    private function uploadedFilePayload(EvaluationImageInput $input, mixed $payload): UploadedFile
    {
        if ($payload instanceof UploadedFile) {
            return $payload;
        }

        throw UnsupportedEvaluationImageInputException::forInputKind($input->kind());
    }
}
