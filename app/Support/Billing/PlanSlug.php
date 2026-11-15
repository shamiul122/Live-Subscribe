<?php

namespace App\Support\Billing;

use App\Models\Plan;
use Illuminate\Support\Str;

final class PlanSlug
{
    /**
     * Build a unique, immutable slug for a plan.
     *
     * Plans are never soft deleted, so uniqueness includes archived plans:
     * slugs stay reserved permanently.
     */
    public static function unique(string $name, ?int $ignorePlanId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'plan';
        }

        $slug = $base;
        $suffix = 1;

        while (self::exists($slug, $ignorePlanId)) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private static function exists(string $slug, ?int $ignorePlanId): bool
    {
        $query = Plan::query()->where('slug', $slug);

        if ($ignorePlanId !== null) {
            $query->whereKeyNot($ignorePlanId);
        }

        return $query->exists();
    }
}
