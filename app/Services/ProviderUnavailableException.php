<?php

namespace App\Services;

use Throwable;

/**
 * Thrown when an AI provider is temporarily unusable (rate-limited, over quota,
 * or unreachable) — as opposed to a genuine "could not understand the food"
 * error. The fallback layer catches this to switch providers.
 *
 * Extends CalorieEstimationException so that, if no fallback is configured, it
 * still surfaces to the controller as a friendly, catchable error.
 */
class ProviderUnavailableException extends CalorieEstimationException
{
    public function __construct(
        string $message = '',
        public readonly bool $quotaExhausted = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
