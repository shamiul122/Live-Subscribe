<?php

namespace App\Services\Templates;

/**
 * Renders message template content by replacing `{{ variable }}` tokens.
 *
 * Rendering is a plain string substitution: there is no eval, no PHP include,
 * and no expression parser. Supported variables fall back to their sample
 * value, and unknown tokens are left untouched so nothing is silently lost.
 */
final class TemplateRendererService
{
    public function __construct(private readonly TemplateVariableService $variables) {}

    /**
     * Render content with the supplied values, falling back to sample values.
     *
     * @param  array<string, string|null>  $values
     */
    public function render(string $content, array $values = []): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function (array $matches) use ($values): string {
                $key = $matches[1];

                if (array_key_exists($key, $values) && $values[$key] !== null) {
                    return (string) $values[$key];
                }

                $sample = $this->variables->sampleValue($key);

                if ($sample !== null) {
                    return $sample;
                }

                // Unknown placeholder: leave it visible rather than dropping it.
                return $matches[0];
            },
            $content,
        );
    }

    /**
     * Render both the subject and the body of a template.
     *
     * @param  array<string, string|null>  $values
     * @return array{subject: string|null, content: string, used_variables: array<int, string>, unknown_variables: array<int, string>}
     */
    public function renderTemplate(string $content, ?string $subject = null, array $values = []): array
    {
        return [
            'subject' => $subject === null ? null : $this->render($subject, $values),
            'content' => $this->render($content, $values),
            'used_variables' => $this->variables->detect($content),
            'unknown_variables' => $this->variables->unknown($content),
        ];
    }

    /**
     * Merge caller supplied values over the safe sample defaults.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public function normalizeValues(array $values): array
    {
        $normalized = $this->variables->sampleValues();

        foreach ($values as $key => $value) {
            if (! $this->variables->isSupported($key)) {
                continue;
            }

            if (is_scalar($value)) {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }
}
