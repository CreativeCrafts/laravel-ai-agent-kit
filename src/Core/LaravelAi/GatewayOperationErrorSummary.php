<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

use Throwable;

/**
 * @internal
 */
final class GatewayOperationErrorSummary
{
    private const int MAX_MESSAGE_LENGTH = 500;

    /**
     * @return array{class: string, summary: string}
     */
    public static function fromThrowable(Throwable $throwable): array
    {
        $message = $throwable->getMessage();
        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = substr($message, 0, self::MAX_MESSAGE_LENGTH).'…';
        }

        return [
            'class' => $throwable::class,
            'summary' => $message,
        ];
    }
}
