<?php

namespace App\Enums;

/**
 * Lifecycle status of a reusable message template.
 *
 * A template never sends a message by itself. Draft templates are still being
 * written, active templates are ready to be reused by other modules, and
 * archived templates are kept for reference but hidden from pickers.
 */
enum MessageTemplateStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    /**
     * Archived templates are read-only until they are restored.
     */
    public function isEditable(): bool
    {
        return $this !== self::Archived;
    }

    /**
     * Only active templates are offered to other modules (campaigns, inbox).
     */
    public function isReusable(): bool
    {
        return $this === self::Active;
    }

    /**
     * All values accepted by validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
