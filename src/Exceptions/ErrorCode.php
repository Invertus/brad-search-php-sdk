<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Exceptions;

/**
 * The machine-readable `code` the search engine returns in an error body, and in
 * each failed item of a bulk-operations response.
 *
 * The engine owns this vocabulary; it is documented in the engine's
 * docs/openapi-v2.yaml. An older engine sends no code, so every caller must
 * tolerate null and fall back to the HTTP status.
 */
enum ErrorCode: string
{
    case ValidationError = 'validation_error';
    case DocumentMissing = 'document_missing';
    case ConfigMalformed = 'config_malformed';
    case ConfigNotFound = 'config_not_found';
    case BackendUnavailable = 'backend_unavailable';
    case PlatformUnavailable = 'platform_unavailable';
    case InternalError = 'internal_error';

    public function failureClass(): FailureClass
    {
        return match ($this) {
            self::ValidationError, self::DocumentMissing => FailureClass::PermanentItem,
            self::ConfigMalformed, self::ConfigNotFound => FailureClass::PermanentConfig,
            self::BackendUnavailable, self::PlatformUnavailable, self::InternalError => FailureClass::Transient,
        };
    }

    /**
     * Resolves a raw code string, returning null for an unknown or missing value
     * so the caller falls back to the HTTP status.
     */
    public static function tryFromNullable(?string $code): ?self
    {
        return $code === null ? null : self::tryFrom($code);
    }
}
