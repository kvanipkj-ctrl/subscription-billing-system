<?php

namespace App\Exceptions;

use RuntimeException;

class UsageIdempotencyConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The idempotency key was already used with different request data.');
    }
}
