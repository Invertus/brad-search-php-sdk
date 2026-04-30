<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Adapters;

use BradSearch\SyncSdk\Exceptions\ValidationException;

class ShopifyAdapter
{
    /** @var array<string, string> memoized option-name slugs */
    private array $optionSlugCache = [];

    public function __construct()
    {
        if (!function_exists('bccomp')) {
            throw new \RuntimeException(
                'ShopifyAdapter requires the bcmath PHP extension for precise price comparisons. ' .
                'Please install or enable ext-bcmath.'
            );
        }
    }

    /**
     * Transform Shopify GraphQL product data to BradSearch format.
     *
     * @param array $shopifyData The Shopify GraphQL API response
     * @param array<string> $locales Locale codes (e.g., ['en', 'lt'] or ['en-US', 'lt-LT'])
     * @return array{products: array, errors: array}
     * @throws ValidationException
     */
    public function transform(array $shopifyData, array $locales = []): array
    {
        if (! isset($shopifyData['data'])) {
            throw new ValidationException('Invalid Shopify data: missing data field');
        }

        if (! isset($shopifyData['data']['products'])) {
            throw new ValidationException('Invalid Shopify data: missing products field');
        }

        if (! isset($shopifyData['data']['products']['edges'])) {
            return ['products' => [], 'errors' => []];
        }

        if (! is_array($shopifyData['data']['products']['edges'])) {
            throw new ValidationException('Invalid Shopify data: products edges must be an array');
        }

        $primaryLocale = $shopifyData['locales']['primary'] ?? ($locales[0] ?? 'en');

        $productCollectionsMap = $this->buildProductCollectionsMap($shopifyData['collections'] ?? []);

        $transformedProducts = [];
        $errors = [];

        foreach ($shopifyData['data']['products']['edges'] as $index => $edge) {
            if (! is_array($edge) || ! isset($edge['node']) || ! is_array($edge['node'])) {
                $errors[] = [
                    'type' => 'invalid_structure',
                    'product_index' => $index,
                    'product_id' => '',
                    'message' => 'Skipping product due to malformed edge or node structure.',
                    'exception' => null,
                ];
                continue;
            }

            try {
                $transformedProducts[] = $this->transformProduct($edge['node'], $locales, $primaryLocale, $productCollectionsMap);
            } catch (\Throwable $e) {
                $errors[] = [
                    'type' => 'transformation_error',
                    'product_index' => $index,
                    'product_id' => $this->getNumericIdOrGid($edge['node']['id'] ?? ''),
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ];
            }
        }

        return ['products' => $transformedProducts, 'errors' => $errors];
    }

    /**
     * Transform a single Shopify product to BradSearch format.
     *
     * @param array<string, mixed> $product Shopify product node (may include translations)
     * @param array<string> $locales Locale codes
     * @param string $primaryLocale Primary locale code
     * @param array<string, array<int, array{default: string, translations: array<string, string>}>> $productCollectionsMap product-GID-keyed list of collection metadata entries
     */
    private function transformProduct(array $product, array $locales, string $primaryLocale, array $productCollectionsMap = []): array
    {
        $price = $this->extractPrice($product);
        $basePrice = $this->extractBasePrice($product);

        $result = [
            'id' => $this->getRequiredField($product, 'id', true),
            'sku' => $this->extractMainSku($product),
            'price' => $price,
            'basePrice' => $basePrice,
            'priceTaxExcluded' => $price,
            'basePriceTaxExcluded' => $basePrice,
            'inStock' => $this->isInStock($product),
            'isNew' => false,
            'variants' => [],
        ];

        $title = $this->getRequiredField($product, 'title');
        $description = $this->extractOptionalString($product, 'descriptionHtml', stripHtml: true);
        $brand = $this->extractOptionalString($product, 'vendor');
        $taxonomyCategory = $this->extractTaxonomyCategory($product);
        $categoryIsTaxonomy = $taxonomyCategory !== '';
        $categoryDefault = $categoryIsTaxonomy
            ? $taxonomyCategory
            : $this->extractOptionalString($product, 'productType');
        $productUrl = $this->extractProductUrl($product);
        $translations = $product['translations'] ?? [];
        $productGid = is_string($product['id'] ?? null) ? $product['id'] : '';
        $productCollections = $productCollectionsMap[$productGid] ?? [];

        $result += ! empty($locales)
            ? $this->buildLocaleFields($locales, $primaryLocale, $translations, $product, $title, $description, $brand, $categoryDefault, $productUrl, $categoryIsTaxonomy, $productCollections)
            : $this->buildPlainFields($title, $description, $brand, $categoryDefault, $productUrl, $product, $productCollections, $primaryLocale);

        if (isset($product['images']) && is_array($product['images'])) {
            $imageUrl = $this->extractImages($product['images']);
            if (! empty($imageUrl)) {
                $result['imageUrl'] = $imageUrl;
            }
        }

        if (isset($product['variants']) && is_array($product['variants'])) {
            $result['variants'] = $this->transformVariants($product['variants'], $locales);
        }

        return $result;
    }

    /**
     * Build locale-suffixed fields for all locales.
     *
     * @param array<int, array{default: string, translations: array<string, string>}> $productCollections
     * @return array<string, mixed>
     */
    private function buildLocaleFields(
        array $locales,
        string $primaryLocale,
        array $translations,
        array $product,
        string $title,
        string $description,
        string $brand,
        string $categoryDefault,
        string $productUrl,
        bool $categoryIsTaxonomy,
        array $productCollections = [],
    ): array {
        $fields = [];

        foreach ($locales as $locale) {
            $localeTranslations = $this->resolveTranslationsForLocale($locale, $primaryLocale, $translations);

            // Translatable fields: title, description, product_type
            $fields["name_{$locale}"] = $this->translated($localeTranslations, 'title') ?? $title;

            $translatedDesc = $this->translated($localeTranslations, 'body_html');
            $localeDescription = $translatedDesc !== null ? strip_tags($translatedDesc) : $description;
            if ($localeDescription !== '') {
                $fields["description_{$locale}"] = $localeDescription;
            }

            // Shopify's Standard Product Taxonomy is global English-only; never translate it.
            // product_type translations only apply to the free-text productType fallback path.
            $localeCategoryDefault = $categoryIsTaxonomy
                ? $categoryDefault
                : ($this->translated($localeTranslations, 'product_type') ?? $categoryDefault);
            $fields["categoryDefault_{$locale}"] = $localeCategoryDefault;
            $fields["categories_{$locale}"] = $this->buildCategories($localeCategoryDefault, $this->extractTags($product));

            // Non-translatable fields: vendor has no Shopify translation support
            if ($brand !== '') {
                $fields["brand_{$locale}"] = $brand;
            }
            if ($productUrl !== '') {
                $fields["productUrl_{$locale}"] = $productUrl;
            }

            $localeCollections = $this->resolveCollectionTitles($productCollections, $locale, $primaryLocale);
            if (!empty($localeCollections)) {
                $fields["collections_{$locale}"] = $localeCollections;
            }
        }

        return $fields;
    }

    /**
     * Build plain (non-localized) fields for backward compatibility.
     *
     * @param array<int, array{default: string, translations: array<string, string>}> $productCollections
     * @return array<string, mixed>
     */
    private function buildPlainFields(
        string $title,
        string $description,
        string $brand,
        string $categoryDefault,
        string $productUrl,
        array $product,
        array $productCollections = [],
        string $primaryLocale = 'en',
    ): array {
        $collections = $this->resolveCollectionTitles($productCollections, $primaryLocale, $primaryLocale);

        return array_filter([
            'name' => $title,
            'description' => $description !== '' ? $description : null,
            'brand' => $brand !== '' ? $brand : null,
            'categoryDefault' => $categoryDefault,
            'categories' => $this->buildCategories($categoryDefault, $this->extractTags($product)),
            'productUrl' => $productUrl !== '' ? $productUrl : null,
            'collections' => !empty($collections) ? $collections : null,
        ], fn($v) => $v !== null);
    }

    /**
     * Invert raw collections metadata into a product-GID-keyed map. Each value is
     * the list of collection-title entries (default + translations) for the
     * collections that product belongs to.
     *
     * The connector emits each collection node with a `product_gids` array
     * covering its full membership (paginated server-side), so this method is
     * the single point at which the join is materialized.
     *
     * @param array<int, array<string, mixed>> $rawCollections
     * @return array<string, array<int, array{default: string, translations: array<string, string>}>>
     */
    private function buildProductCollectionsMap(array $rawCollections): array
    {
        $map = [];

        foreach ($rawCollections as $collection) {
            if (!is_array($collection)) {
                continue;
            }

            $defaultTitle = $collection['title'] ?? null;
            if (!is_string($defaultTitle) || $defaultTitle === '') {
                continue;
            }

            $translationsByLocale = [];
            $rawTranslations = $collection['translations'] ?? [];
            if (is_array($rawTranslations)) {
                foreach ($rawTranslations as $locale => $entries) {
                    if (!is_string($locale) || !is_array($entries)) {
                        continue;
                    }
                    foreach ($entries as $entry) {
                        if (is_array($entry)
                            && ($entry['key'] ?? null) === 'title'
                            && is_string($entry['value'] ?? null)
                            && $entry['value'] !== ''
                        ) {
                            $translationsByLocale[$locale] = $entry['value'];
                            break;
                        }
                    }
                }
            }

            $entry = ['default' => $defaultTitle, 'translations' => $translationsByLocale];

            $productGids = $collection['product_gids'] ?? [];
            if (!is_array($productGids)) {
                continue;
            }

            foreach ($productGids as $productGid) {
                if (!is_string($productGid) || $productGid === '') {
                    continue;
                }
                $map[$productGid][] = $entry;
            }
        }

        return $map;
    }

    /**
     * Resolve titles for a product's collections in a given locale.
     * Falls back to the default title when a translation is missing.
     *
     * @param array<int, array{default: string, translations: array<string, string>}> $productCollections
     * @return array<string>
     */
    private function resolveCollectionTitles(array $productCollections, string $locale, string $primaryLocale): array
    {
        if (empty($productCollections)) {
            return [];
        }

        $titles = [];
        foreach ($productCollections as $entry) {
            $title = $locale === $primaryLocale
                ? $entry['default']
                : ($entry['translations'][$locale] ?? $entry['default']);

            if ($title !== '') {
                $titles[] = $title;
            }
        }

        return array_values(array_unique($titles));
    }

    /**
     * Resolve translation data for a locale. Returns null for primary locale (use native fields).
     *
     * @return array<int, array{key: string, value: string}>|null
     */
    private function resolveTranslationsForLocale(string $locale, string $primaryLocale, array $translations): ?array
    {
        if ($locale === $primaryLocale) {
            return null;
        }

        return (isset($translations[$locale]) && is_array($translations[$locale]))
            ? $translations[$locale]
            : null;
    }

    /**
     * Get a translated value, returning null for primary locale or when no translation exists.
     */
    private function translated(?array $localeTranslations, string $key): ?string
    {
        if ($localeTranslations === null) {
            return null;
        }

        $match = array_find(
            $localeTranslations,
            fn($entry) => is_array($entry)
                && ($entry['key'] ?? null) === $key
                && is_string($entry['value'] ?? null)
                && $entry['value'] !== ''
        );

        return $match['value'] ?? null;
    }

    /**
     * Extract an optional string field, returning empty string if missing/empty.
     */
    private function extractOptionalString(array $data, string $field, bool $stripHtml = false): string
    {
        if (! isset($data[$field]) || ! is_string($data[$field]) || $data[$field] === '') {
            return '';
        }

        return $stripHtml ? strip_tags($data[$field]) : $data[$field];
    }

    /**
     * Extract a hierarchical category string from Shopify's Standard Product Taxonomy.
     *
     * Prefers `fullName` ("Apparel & Accessories > Clothing > Shirts") over `name` ("Shirts").
     * Returns '' when the field is missing, malformed, or the "Uncategorized" literal —
     * callers should fall back to the free-text `productType` in that case.
     */
    private function extractTaxonomyCategory(array $product): string
    {
        if (! isset($product['category']) || ! is_array($product['category'])) {
            return '';
        }

        $category = $product['category'];

        // Single global "Uncategorized" GID — treat as no category to avoid a junk facet bucket.
        if (($category['id'] ?? null) === 'gid://shopify/TaxonomyCategory/na') {
            return '';
        }

        $fullName = $category['fullName'] ?? null;
        if (is_string($fullName) && $fullName !== '') {
            return $fullName;
        }

        $name = $category['name'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return '';
    }

    /**
     * Extract product URL from onlineStoreUrl field.
     */
    private function extractProductUrl(array $product): string
    {
        return $this->extractOptionalString($product, 'onlineStoreUrl')
            ?: $this->extractOptionalString($product, 'onlineStorePreviewUrl');
    }

    /**
     * Extract tags from product.
     *
     * @return array<string>
     */
    private function extractTags(array $product): array
    {
        return isset($product['tags']) && is_array($product['tags']) ? $product['tags'] : [];
    }

    /**
     * Build categories from a product type string and tags.
     * Shared by both locale and non-locale code paths.
     *
     * @param array<string> $tags
     * @return array<string>
     */
    private function buildCategories(string $productType, array $tags): array
    {
        $raw = $productType !== '' ? [$productType, ...$tags] : $tags;
        $valid = array_filter($raw, fn($c) => is_string($c) && $c !== '');

        return array_values(array_unique($valid));
    }

    /**
     * Extract numeric ID from Shopify GID format.
     * Converts "gid://shopify/Product/6843600694995" to "6843600694995".
     */
    private function extractNumericId(string $gid): string
    {
        if ($gid === '') {
            return '';
        }

        if (preg_match('#/(\d+)$#', $gid, $matches)) {
            return $matches[1];
        }

        throw new ValidationException("Malformed Shopify GID: {$gid}");
    }

    /**
     * Safely extract numeric ID or return raw GID for error logging.
     */
    private function getNumericIdOrGid(string $gid): string
    {
        if ($gid === '') {
            return '';
        }
        try {
            return $this->extractNumericId($gid);
        } catch (ValidationException) {
            return $gid;
        }
    }

    /**
     * Extract main SKU from the first variant.
     */
    private function extractMainSku(array $product): string
    {
        $sku = $this->getNestedValue($product, ['variants', 'edges', 0, 'node', 'sku']);
        return is_string($sku) ? $sku : '';
    }

    /**
     * Extract minimum price from priceRangeV2.
     */
    private function extractPrice(array $product): string
    {
        $amount = $this->getNestedValue($product, ['priceRangeV2', 'minVariantPrice', 'amount']);
        return is_string($amount) ? $amount : '0.00';
    }

    /**
     * Extract base price from variants' compareAtPrice.
     * Uses the maximum compareAtPrice across all variants.
     */
    private function extractBasePrice(array $product): string
    {
        $edges = $this->getNestedValue($product, ['variants', 'edges'], []);

        $compareAtPrices = array_filter(
            array_map(fn($edge) => $this->getNestedValue($edge, ['node', 'compareAtPrice']), $edges),
            fn($price) => $price !== null
        );

        if (! empty($compareAtPrices)) {
            $max = array_reduce($compareAtPrices, fn($carry, $price) =>
                bccomp((string) $price, $carry, 2) > 0 ? (string) $price : $carry, '0.00');

            if (bccomp($max, '0.00', 2) > 0) {
                return $max;
            }
        }

        $amount = $this->getNestedValue($product, ['priceRangeV2', 'maxVariantPrice', 'amount']);

        return is_string($amount) ? $amount : $this->extractPrice($product);
    }

    /**
     * Safely access nested array values.
     */
    private function getNestedValue(array $data, array $keys, mixed $default = null): mixed
    {
        $current = $data;

        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Check if product is in stock based on variants.
     */
    private function isInStock(array $product): bool
    {
        return array_any(
            $this->getNestedValue($product, ['variants', 'edges'], []),
            fn($edge) => $this->getNestedValue($edge, ['node', 'availableForSale']) === true
        );
    }

    /**
     * Extract and format images.
     */
    private function extractImages(array $imagesData): array
    {
        if (! isset($imagesData['edges']) || ! is_array($imagesData['edges'])) {
            return [];
        }

        $images = array_filter(array_map(
            fn($edge) => isset($edge['node']['url']) && is_string($edge['node']['url'])
                ? ['url' => $edge['node']['url'], 'width' => $edge['node']['width'] ?? 0, 'height' => $edge['node']['height'] ?? 0]
                : null,
            $imagesData['edges']
        ));

        if (empty($images)) {
            return [];
        }

        // Sort by width ascending; pick smallest for "small" and middle for "medium" (best-effort, Shopify has no explicit size categories)
        usort($images, fn($a, $b) => $a['width'] <=> $b['width']);
        $images = array_values($images);

        return [
            'small' => $images[0]['url'],
            'medium' => $images[(int) floor(count($images) / 2)]['url'],
        ];
    }

    /**
     * Transform Shopify variants to BradSearch format.
     */
    private function transformVariants(array $variantsData, array $locales): array
    {
        if (! isset($variantsData['edges']) || ! is_array($variantsData['edges'])) {
            return [];
        }

        return array_map(function (array $edge) use ($locales): array {
            if (! isset($edge['node']) || ! is_array($edge['node'])) {
                throw new ValidationException('Variant has malformed node structure');
            }

            $variant = $edge['node'];

            if (! isset($variant['id'])) {
                throw new ValidationException('Variant is missing required ID field');
            }

            $options = $variant['selectedOptions'] ?? [];

            return [
                'id' => $this->extractNumericId($variant['id']),
                'sku' => $variant['sku'] ?? '',
                ...! empty($locales)
                    ? ['attrs' => $this->transformVariantOptionsWithLocales($options, $locales)]
                    : ['attributes' => $this->transformVariantOptions($options)],
            ];
        }, $variantsData['edges']);
    }

    /**
     * Check if an option entry is a valid non-empty name/value pair.
     */
    private function isValidOption(mixed $option): bool
    {
        return is_array($option)
            && isset($option['name'], $option['value'])
            && is_string($option['name']) && $option['name'] !== ''
            && is_string($option['value']) && $option['value'] !== '';
    }

    /**
     * Output: {"color": {"en-US": "White", "lt-LT": "White"}}
     *
     * @return array<string, array<string, string>>
     */
    private function transformVariantOptionsWithLocales(array $selectedOptions, array $locales): array
    {
        $validOptions = array_filter($selectedOptions, $this->isValidOption(...));

        $result = [];
        foreach ($validOptions as $option) {
            $result[$this->slugifyOptionName($option['name'])] = array_fill_keys($locales, $option['value']);
        }

        return $result;
    }

    /**
     * Transform variant selectedOptions to flat attributes format (no locales).
     *
     * @return array<int, array{name: string, value: string}>
     */
    private function transformVariantOptions(array $selectedOptions): array
    {
        return array_values(array_map(
            fn($option) => [
                'name' => $this->slugifyOptionName($option['name']),
                'value' => $option['value'],
            ],
            array_filter($selectedOptions, $this->isValidOption(...))
        ));
    }

    /**
     * Must match byte-for-byte:
     *   bradsearch-shopify-app1 app/Http/Controllers/API/AttributesController.php::slugify()
     * See docs/SLUG_RULE.md in that repo.
     *
     * Memoized per adapter instance: one compute per unique option name,
     * not per variant × option. Cache is pure (slug is a deterministic
     * function of name), so cross-product reuse within a sync is safe.
     */
    private function slugifyOptionName(string $name): string
    {
        if (isset($this->optionSlugCache[$name])) {
            return $this->optionSlugCache[$name];
        }

        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower(trim($name), 'UTF-8'));
        $slug = trim((string) $slug, '-');

        return $this->optionSlugCache[$name] = $slug === '' ? 'unknown' : $slug;
    }

    /**
     * Get required field with validation.
     */
    private function getRequiredField(array $data, string $field, bool $extractId = false): string
    {
        if (! isset($data[$field]) || ! is_scalar($data[$field])) {
            throw new ValidationException("Required field '{$field}' is missing or not a scalar in Shopify data");
        }

        $value = (string) $data[$field];

        return $extractId ? $this->extractNumericId($value) : $value;
    }
}
