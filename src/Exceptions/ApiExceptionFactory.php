<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * Builds the right ApiException subclass for a non-2xx engine response.
 *
 * The engine's `code` decides when it is present and recognised; the HTTP status
 * decides otherwise. That ordering is what lets a new engine code reach the right
 * exception, while an older engine that sends no code still classifies correctly.
 */
final class ApiExceptionFactory
{
    public static function fromResponse(int $statusCode, ?string $body): ApiException
    {
        $errorCode = self::extractCode($body);
        $message = "API request failed with status {$statusCode}";

        $class = self::classFor($statusCode, ErrorCode::tryFromNullable($errorCode));

        return new $class($message, $statusCode, $body, null, $errorCode);
    }

    /**
     * @return class-string<ApiException>
     */
    private static function classFor(int $statusCode, ?ErrorCode $code): string
    {
        // A recognised code is more specific than the status it arrived with.
        if ($code !== null) {
            $byCode = match ($code) {
                ErrorCode::ValidationError, ErrorCode::ConfigMalformed => ValidationFailedException::class,
                ErrorCode::ConfigNotFound, ErrorCode::DocumentMissing => NotFoundException::class,
                ErrorCode::BackendUnavailable, ErrorCode::PlatformUnavailable, ErrorCode::InternalError => TransientApiException::class,
            };

            // Never let a code downgrade a 401/403 into something unauthenticated
            // callers would retry.
            if ($statusCode !== 401 && $statusCode !== 403) {
                return $byCode;
            }
        }

        return match (true) {
            $statusCode === 401, $statusCode === 403 => UnauthorizedException::class,
            $statusCode === 404 => NotFoundException::class,
            $statusCode === 400, $statusCode === 422 => ValidationFailedException::class,
            $statusCode === 429, $statusCode >= 500 => TransientApiException::class,
            default => ApiException::class,
        };
    }

    /**
     * Reads `code` out of an engine error envelope. Returns null for a body that
     * is absent, not JSON, or not a JSON object.
     */
    private static function extractCode(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return null;
        }

        $code = $decoded['code'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }
}
