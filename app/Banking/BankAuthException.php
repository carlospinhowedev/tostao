<?php

namespace App\Banking;

/** The bank refused access: the consent expired, was revoked, or never worked. */
class BankAuthException extends BankException {}
