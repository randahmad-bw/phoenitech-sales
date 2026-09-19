<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a request is well-formed and authorized but violates a business
 * rule (e.g. deleting the last super admin, deleting a role still in use).
 *
 * Rendered as a 409 Conflict ApiResponse — see bootstrap/app.php.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
