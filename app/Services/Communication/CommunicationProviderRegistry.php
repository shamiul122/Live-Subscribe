<?php

namespace App\Services\Communication;

use App\Contracts\Communication\CommunicationProviderInterface;
use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;

/**
 * Registry of communication adapters keyed by provider identifier.
 */
final class CommunicationProviderRegistry
{
    /** @var array<string, CommunicationProviderInterface> */
    private array $providers = [];

    public function register(string $identifier, CommunicationProviderInterface $provider): void
    {
        $this->providers[$identifier] = $provider;
    }

    public function provider(string $identifier): ?CommunicationProviderInterface
    {
        return $this->providers[strtolower(trim($identifier))] ?? null;
    }

    public function providerFor(SocialProvider $provider): ?CommunicationProviderInterface
    {
        return $this->provider($provider->value);
    }

    public function providerForAccount(ConnectedAccount $account): ?CommunicationProviderInterface
    {
        return $this->provider($account->provider->value);
    }

    /**
     * @return array<int, CommunicationProviderInterface>
     */
    public function providers(): array
    {
        return array_values($this->providers);
    }

    public function registerDefaults(): void
    {
        $factory = app(CommunicationProviderFactory::class);

        foreach (SocialProvider::cases() as $provider) {
            if ($this->provider($provider->value) === null) {
                $this->register($provider->value, $factory->make($provider));
            }
        }
    }
}
