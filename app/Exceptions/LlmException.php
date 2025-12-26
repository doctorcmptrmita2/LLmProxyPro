<?php

namespace App\Exceptions;

use Exception;

class LlmException extends Exception
{
    public function __construct(
        public string $errorType,
        string $message = '',
        public ?int $statusCode = null,
        public ?array $details = null,
    ) {
        parent::__construct($message);
    }
}

