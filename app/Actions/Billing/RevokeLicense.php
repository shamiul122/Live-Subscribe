<?php

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\EnsuresBillingPrivileges;
use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently revoke a license. Revocation is terminal.
 */
class RevokeLicense
{
    use EnsuresBillingPrivileges;

    public function handle(License $license, User $actor): License
    {
        $organization = $license->organization()->firstOrFail();

        $this->ensureBillingManager($organization, $actor);

        return DB::transaction(function () use ($license, $actor): License {
            $locked = License::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === LicenseStatus::Revoked) {
                return $locked;
            }

            $locked->fill([
                'status' => LicenseStatus::Revoked,
                'revoked_at' => now(),
                'revoked_by_id' => $actor->id,
            ]);

            $locked->save();

            return $locked->refresh();
        });
    }
}
