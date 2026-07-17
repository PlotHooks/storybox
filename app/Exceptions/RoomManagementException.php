<?php

namespace App\Exceptions;

use Exception;

class RoomManagementException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $friendlyMessage,
        public readonly int $status,
        public readonly array $fields = [],
    ) {
        parent::__construct($friendlyMessage);
    }
}
