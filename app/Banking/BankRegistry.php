<?php

namespace App\Banking;

use InvalidArgumentException;

/**
 * Knows which providers exist (config/tostao.php) and which of them can be
 * used right now. Adding a provider is one class and one line of config.
 */
class BankRegistry
{
    /** @var array<string, BankProvider> */
    private array $resolved = [];

    /** @return array<string, BankProvider> the providers that are configured, keyed by key() */
    public function available(): array
    {
        $out = [];

        foreach (array_keys((array) config('tostao.banks.providers')) as $key) {
            $provider = $this->get($key);
            if ($provider->isConfigured()) {
                $out[$provider->key()] = $provider;
            }
        }

        return $out;
    }

    public function enabled(): bool
    {
        return $this->available() !== [];
    }

    public function get(string $key): BankProvider
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $class = config("tostao.banks.providers.{$key}.class");
        if (! is_string($class) || ! is_a($class, BankProvider::class, true)) {
            throw new InvalidArgumentException("Unknown bank provider [{$key}].");
        }

        return $this->resolved[$key] = app($class);
    }
}
