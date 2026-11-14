<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Str;

final class OrganizationSlug
{
    /**
     * Build a unique slug for an organization.
     *
     * Soft-deleted organizations keep their slug reserved. This is deliberate:
     * slugs are part of public URLs and re-use could expose stale links to a
     * newly created organization. Uniqueness therefore includes trashed rows.
     */
    public static function unique(string $name, ?int $ignoreOrganizationId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'organization';
        }

        $slug = $base;
        $suffix = 1;

        while (self::exists($slug, $ignoreOrganizationId)) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private static function exists(string $slug, ?int $ignoreOrganizationId): bool
    {
        $query = Organization::withTrashed()->where('slug', $slug);

        if ($ignoreOrganizationId !== null) {
            $query->whereKeyNot($ignoreOrganizationId);
        }

        return $query->exists();
    }
}
