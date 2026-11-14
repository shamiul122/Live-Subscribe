<?php

namespace App\Services\Social;

use App\Contracts\Social\SocialAccountProviderInterface;
use App\Enums\SocialProvider;
use InvalidArgumentException;

/**
 * Registry of supported social account providers.
 *
 * Built-in providers are registered as singletons; additional providers can be
 * registered at runtime through the container without touching the core
 * architecture.
 */
final class SocialAccountRegistry
{
    /** @var array<string, SocialAccountProviderInterface> */
    private array $providers = [];

    /**
     * Register a provider instance under its stable identifier.
     */
    public function register(string $identifier, SocialAccountProviderInterface $provider): void
    {
        $this->providers[$identifier] = $provider;
    }

    /**
     * Resolve a provider by identifier, or null when unknown.
     */
    public function provider(string $identifier): ?SocialAccountProviderInterface
    {
        $normalized = strtolower(trim($identifier));

        if (array_key_exists($normalized, $this->providers)) {
            return $this->providers[$normalized];
        }

        return null;
    }

    /**
     * Resolve a provider enum instance, or null when unknown.
     */
    public function providerFor(SocialProvider $provider): ?SocialAccountProviderInterface
    {
        return $this->provider($provider->value);
    }

    /**
     * All registered provider instances.
     *
     * @return array<int, SocialAccountProviderInterface>
     */
    public function providers(): array
    {
        return array_values($this->providers);
    }

    /**
     * Register the default built-in providers.
     */
    public function registerDefaults(): void
    {
        foreach (SocialProvider::cases() as $provider) {
            $this->register($provider->value, app(SocialProviderFactory::class)->make($provider));
        }
    }
}
