<?php

namespace App\Banking;

use Carbon\CarbonInterface;

/**
 * One way of reaching banks (an open-banking aggregator, a bank's own API...).
 *
 * The app never talks to a bank directly: it talks to this interface. To add a
 * provider, implement it and register the class in config/tostao.php. Which
 * banks are available is not hard-coded anywhere: it is whatever
 * institutions() returns.
 */
interface BankProvider
{
    /** Stable identifier stored in the database ("enablebanking"). */
    public function key(): string;

    /** Name shown to people. */
    public function label(): string;

    /** True when the credentials this provider needs are present. */
    public function isConfigured(): bool;

    /** @return list<Institution> the banks reachable in a country (ISO 3166 alpha-2) */
    public function institutions(string $country): array;

    /** Begin the consent: where to send the person to authorise access. */
    public function startConsent(string $institutionId, string $state, string $redirectUrl, CarbonInterface $validUntil): ConsentStart;

    /** Finish the consent with the code the bank sent back. */
    public function completeConsent(string $code): Consent;

    /**
     * Booked transactions of one account since a date (inclusive).
     *
     * @return list<BankTransaction>
     *
     * @throws BankAuthException when the consent expired or was revoked
     * @throws BankException on any other failure
     */
    public function transactions(Consent $consent, string $accountId, CarbonInterface $from): array;
}
