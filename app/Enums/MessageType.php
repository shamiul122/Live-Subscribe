<?php

namespace App\Enums;

/**
 * Message content type for an inbox message.
 */
enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Location = 'location';
    case System = 'system';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Image => 'Image',
            self::Video => 'Video',
            self::Audio => 'Audio',
            self::Document => 'Document',
            self::Location => 'Location',
            self::System => 'System',
            self::Unknown => 'Unknown',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
