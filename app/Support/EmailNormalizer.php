<?php

namespace App\Support;

use Illuminate\Support\Str;

final class EmailNormalizer
{
    /**
     * Normalize an email address for storage and comparison.
     *
     * Email addresses are stored lower-cased and trimmed so that uniqueness
     * checks and invitation matching do not depend on database collation.
     */
    public static function normalize(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = Str::lower(trim($email));

        return $normalized === '' ? null : $normalized;
    }
}
