<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\V2\ValueObjects\Product;

use BradSearch\SyncSdk\V2\Exceptions\InvalidArgumentException;
use BradSearch\SyncSdk\V2\ValueObjects\ValueObject;

/**
 * Represents an image URL structure with multiple size variants.
 *
 * This immutable ValueObject contains URLs for different image sizes,
 * matching the API's image_url field type format with size keys.
 */
final readonly class ImageUrl extends ValueObject
{
    private const VALID_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'heic'];

    /**
     * @param string $small URL for small image size (required)
     * @param string $medium URL for medium image size (required)
     * @param string|null $large URL for large image size (optional)
     * @param string|null $thumbnail URL for thumbnail image size (optional)
     */
    public function __construct(
        public string $small,
        public string $medium,
        public ?string $large = null,
        public ?string $thumbnail = null
    ) {
        $this->validateUrl($small, 'small');
        $this->validateUrl($medium, 'medium');

        if ($large !== null) {
            $this->validateUrl($large, 'large');
        }

        if ($thumbnail !== null) {
            $this->validateUrl($thumbnail, 'thumbnail');
        }
    }

    /**
     * Returns a new instance with a different small URL.
     */
    public function withSmall(string $small): self
    {
        return new self($small, $this->medium, $this->large, $this->thumbnail);
    }

    /**
     * Returns a new instance with a different medium URL.
     */
    public function withMedium(string $medium): self
    {
        return new self($this->small, $medium, $this->large, $this->thumbnail);
    }

    /**
     * Returns a new instance with a different large URL.
     */
    public function withLarge(?string $large): self
    {
        return new self($this->small, $this->medium, $large, $this->thumbnail);
    }

    /**
     * Returns a new instance with a different thumbnail URL.
     */
    public function withThumbnail(?string $thumbnail): self
    {
        return new self($this->small, $this->medium, $this->large, $thumbnail);
    }

    /**
     * Creates an ImageUrl instance from an array (e.g., from JSON serialization).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['small'] ?? '',
            $data['medium'] ?? '',
            $data['large'] ?? null,
            $data['thumbnail'] ?? null
        );
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        $result = [
            'small' => $this->small,
            'medium' => $this->medium,
        ];

        if ($this->large !== null) {
            $result['large'] = $this->large;
        }

        if ($this->thumbnail !== null) {
            $result['thumbnail'] = $this->thumbnail;
        }

        return $result;
    }

    /**
     * Validates that a URL is a valid image URL format.
     *
     * @throws InvalidArgumentException If URL is invalid
     */
    private function validateUrl(string $url, string $fieldName): void
    {
        if (trim($url) === '') {
            throw new InvalidArgumentException(
                sprintf('The %s URL cannot be empty.', $fieldName),
                $fieldName,
                $url
            );
        }

        if (!preg_match('/^https?:\/\/.+/', $url)) {
            throw new InvalidArgumentException(
                sprintf('The %s URL must be a valid HTTP or HTTPS URL.', $fieldName),
                $fieldName,
                $url
            );
        }

        $path = parse_url($url, PHP_URL_PATH);
        if ($path !== null && $path !== '') {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($extension !== '' && !in_array($extension, self::VALID_EXTENSIONS, true)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'The %s URL must have a valid image extension (%s), got "%s".',
                        $fieldName,
                        implode(', ', self::VALID_EXTENSIONS),
                        $extension
                    ),
                    $fieldName,
                    $url
                );
            }
        }
    }
}
