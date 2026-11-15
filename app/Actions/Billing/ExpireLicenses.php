<?php

namespace App\Actions\Billing;

use App\Enums\LicenseStatus;
use App\Models\License;

/**
 * Expire active licenses whose expiry date has passed.
 */
class ExpireLicenses
{
    public function handle(): int
    {
        return License::query()
            ->where('status', LicenseStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => LicenseStatus::Expired->value,
                'updated_at' => now(),
            ]);
    }
}
