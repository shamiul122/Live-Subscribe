<?php

namespace App\Services\Templates;

/**
 * Knows the placeholder vocabulary a message template may use.
 *
 * The service is intentionally declarative: it never executes template content
 * and never calls eval. It only recognises `{{ snake_case }}` tokens, reports
 * which are supported, and provides safe sample values for previews.
 */
final class TemplateVariableService
{
    /**
     * The supported placeholders, keyed by their token name.
     *
     * @var array<string, array{label: string, sample: string, description: string}>
     */
    private const SUPPORTED = [
        'customer_name' => [
            'label' => 'Customer name',
            'sample' => 'Jane Doe',
            'description' => 'The full name of the recipient.',
        ],
        'customer_email' => [
            'label' => 'Customer email',
            'sample' => 'jane@example.com',
            'description' => 'The email address of the recipient.',
        ],
        'product_name' => [
            'label' => 'Product name',
            'sample' => 'Acme Pro Plan',
            'description' => 'The product the message refers to.',
        ],
        'order_number' => [
            'label' => 'Order number',
            'sample' => 'ORD-1042',
            'description' => 'A reference for a specific order.',
        ],
        'company_name' => [
            'label' => 'Company name',
            'sample' => 'Acme Inc.',
            'description' => 'The sending organization name.',
        ],
    ];

    /**
     * Matches `{{ variable }}` tokens. Only word characters are allowed so the
     * syntax is unambiguous and cannot be used to smuggle expressions.
     */
    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/';

    /**
     * The full catalogue of supported variables for editors and pickers.
     *
     * @return array<int, array{key: string, label: string, sample: string, description: string}>
     */
    public function supported(): array
    {
        $variables = [];

        foreach (self::SUPPORTED as $key => $meta) {
            $variables[] = [
                'key' => $key,
                'label' => $meta['label'],
                'sample' => $meta['sample'],
                'description' => $meta['description'],
            ];
        }

        return $variables;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys(self::SUPPORTED);
    }

    public function isSupported(string $key): bool
    {
        return array_key_exists($key, self::SUPPORTED);
    }

    public function sampleValue(string $key): ?string
    {
        return self::SUPPORTED[$key]['sample'] ?? null;
    }

    /**
     * Detect the unique placeholders used in a piece of content, in order.
     *
     * @return array<int, string>
     */
    public function detect(string $content): array
    {
        $matches = [];

        if (preg_match_all(self::PATTERN, $content, $matches) === false) {
            return [];
        }

        $detected = [];

        foreach ($matches[1] as $key) {
            $detected[$key] = $key;
        }

        return array_values($detected);
    }

    /**
     * The placeholders that are used but not part of the supported vocabulary.
     *
     * @return array<int, string>
     */
    public function unknown(string $content): array
    {
        return array_values(array_filter(
            $this->detect($content),
            fn (string $key): bool => ! $this->isSupported($key),
        ));
    }

    /**
     * A safe sample value map for every supported variable.
     *
     * @return array<string, string>
     */
    public function sampleValues(): array
    {
        return array_map(
            static fn (array $meta): string => $meta['sample'],
            self::SUPPORTED,
        );
    }

    /**
     * Describe a set of keys for the frontend, skipping unknown keys.
     *
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, label: string, sample: string, description: string}>
     */
    public function describe(array $keys): array
    {
        $described = [];

        foreach ($keys as $key) {
            if (! $this->isSupported($key)) {
                continue;
            }

            $described[] = [
                'key' => $key,
                'label' => self::SUPPORTED[$key]['label'],
                'sample' => self::SUPPORTED[$key]['sample'],
                'description' => self::SUPPORTED[$key]['description'],
            ];
        }

        return $described;
    }
}
