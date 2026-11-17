<?php

namespace App\Brokers;

use InvalidArgumentException;

/** The brokers listed in config/tostao.php, and which of them have credentials. */
class BrokerRegistry
{
    /** @var array<string, BrokerProvider> */
    private array $resolved = [];

    /** @return array<string, BrokerProvider> */
    public function available(): array
    {
        $out = [];

        foreach (array_keys((array) config('tostao.brokers.providers')) as $key) {
            $provider = $this->get($key);
            if ($provider->isConfigured()) {
                $out[$provider->key()] = $provider;
            }
        }

        return $out;
    }

    public function get(string $key): BrokerProvider
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $class = config("tostao.brokers.providers.{$key}.class");
        if (! is_string($class) || ! is_a($class, BrokerProvider::class, true)) {
            throw new InvalidArgumentException("Unknown broker provider [{$key}].");
        }

        return $this->resolved[$key] = app($class);
    }
}
