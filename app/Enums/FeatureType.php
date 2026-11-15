<?php

namespace App\Enums;

enum FeatureType: string
{
    case Boolean = 'boolean';
    case Metered = 'metered';
    case Resource = 'resource';

    /**
     * Human readable label for the feature type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Boolean',
            self::Metered => 'Metered',
            self::Resource => 'Resource capacity',
        };
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
