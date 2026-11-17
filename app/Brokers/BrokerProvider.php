<?php

namespace App\Brokers;

use App\Banking\BankAuthException;
use App\Banking\BankException;
use App\Banking\BankTransaction;
use Carbon\CarbonInterface;

/**
 * An investment broker with an API. Unlike a bank there is no per-person consent
 * to renew: you create an API key in the broker's app and put it in .env.
 *
 * The app only asks two things of a broker: what the account is worth now, and
 * which money went in or out. Individual trades are deliberately out of scope:
 * buying shares is not spending, and a household budget has no use for them.
 */
interface BrokerProvider
{
    public function key(): string;

    public function label(): string;

    public function isConfigured(): bool;

    /** Total value of the account (cash and investments), in cents of its own currency. */
    public function valueCents(): int;

    /**
     * Money deposited into or withdrawn from the broker since a date (inclusive).
     * Deposits are positive, withdrawals negative.
     *
     * @return list<BankTransaction>
     *
     * @throws BankAuthException when the key is wrong or lacks permission
     * @throws BankException on any other failure
     */
    public function cashMovements(CarbonInterface $from): array;
}
