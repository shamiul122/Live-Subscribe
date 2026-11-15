<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\User;
use App\Support\Billing\LicenseKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rotate a license key.
 *
 * Rotation replaces the stored hash, so the previous raw key stops working
 * immediately. The new raw key is returned once.
 */
class RotateLicenseKey
{
    use EnsuresBillingPrivileges;

    public function handle(License $license, User $actor): License
    {
        $organization = $license->organization()->firstOrFail();

        $this->ensureBillingManager($organization, $actor);

        return DB::transaction(function () use ($license): License {
            $locked = License::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === LicenseStatus::Revoked) {
                throw ValidationException::withMessages([
                    'license' => 'A revoked license cannot be rotated.',
                ]);
            }

            $raw = LicenseKey::generate();

            $locked->key_hash = LicenseKey::hash($raw);
            $locked->key_prefix = LicenseKey::prefix($raw);
            $locked->key_last_four = LicenseKey::lastFour($raw);
            $locked->save();

            $locked->plainKey = $raw;

            return $locked->refresh();
        });
    }
}
