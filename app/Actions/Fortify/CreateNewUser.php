<?php

namespace App\Actions\Fortify;

use App\Actions\Organizations\ProvisionPersonalOrganization;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly ProvisionPersonalOrganization $provisionPersonalOrganization,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * The user, their personal organization and the owner membership are
     * created in a single transaction. If provisioning fails the registration
     * is rolled back completely.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['email'] = EmailNormalizer::normalize($input['email'] ?? null) ?? '';

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $this->provisionPersonalOrganization->handle($user);

            return $user;
        });
    }
}
