<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use Laravel\Ai\Files\Base64Audio;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\LocalAudio;
use Laravel\Ai\Files\LocalDocument;
use Laravel\Ai\Files\LocalImage;
use Laravel\Ai\Files\ProviderDocument;
use Laravel\Ai\Files\ProviderImage;
use Laravel\Ai\Files\RemoteAudio;
use Laravel\Ai\Files\RemoteDocument;
use Laravel\Ai\Files\RemoteImage;
use Laravel\Ai\Files\StoredAudio;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;

/**
 * Serializes Laravel AI {@see File} attachments to JSON-friendly arrays for persistence / replay.
 */
final class PersistedLaravelAiFileSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(File $file): array
    {
        if ($file instanceof Arrayable) {
            /** @var array<string, mixed> $data */
            $data = $file->toArray();

            return $data;
        }

        throw new InvalidArgumentException(sprintf(
            'Attachment of type [%s] is not serializable for conversation persistence.',
            $file::class,
        ));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): File
    {
        $type = $data['type'] ?? null;

        if (!is_string($type) || $type === '') {
            throw new InvalidArgumentException('Persisted attachment payload must include a non-empty string "type".');
        }

        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : null;
        $mime = isset($data['mime']) && is_string($data['mime']) ? $data['mime'] : null;

        return match ($type) {
            'base64-image' => (new Base64Image(
                self::stringField($data, 'base64'),
                $mime,
            ))->as($name),
            'base64-document' => (new Base64Document(
                self::stringField($data, 'base64'),
                $mime,
            ))->as($name),
            'base64-audio' => (new Base64Audio(
                self::stringField($data, 'base64'),
                $mime,
            ))->as($name),
            'remote-image' => (new RemoteImage(
                self::stringField($data, 'url'),
                $mime,
            ))->as($name),
            'remote-document' => (new RemoteDocument(
                self::stringField($data, 'url'),
                $mime,
            ))->as($name),
            'remote-audio' => (new RemoteAudio(
                self::stringField($data, 'url'),
                $mime,
            ))->as($name),
            'local-image' => (new LocalImage(
                self::stringField($data, 'path'),
                $mime,
            ))->as($name),
            'local-document' => (new LocalDocument(
                self::stringField($data, 'path'),
                $mime,
            ))->as($name),
            'local-audio' => (new LocalAudio(
                self::stringField($data, 'path'),
                $mime,
            ))->as($name),
            'stored-image' => (new StoredImage(
                self::stringField($data, 'path'),
                isset($data['disk']) && is_string($data['disk']) ? $data['disk'] : null,
            ))->as($name),
            'stored-document' => (new StoredDocument(
                self::stringField($data, 'path'),
                isset($data['disk']) && is_string($data['disk']) ? $data['disk'] : null,
            ))->as($name),
            'stored-audio' => (new StoredAudio(
                self::stringField($data, 'path'),
                isset($data['disk']) && is_string($data['disk']) ? $data['disk'] : null,
            ))->as($name),
            'provider-image' => (new ProviderImage(self::stringField($data, 'id')))
                ->as($name),
            'provider-document' => (new ProviderDocument(self::stringField($data, 'id')))
                ->as($name),
            default => throw new InvalidArgumentException(sprintf('Unknown persisted attachment type [%s].', $type)),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringField(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
