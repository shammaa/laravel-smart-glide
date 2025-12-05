<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Exceptions;

use InvalidArgumentException;

class InvalidImageException extends InvalidArgumentException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message, ?string $path = null)
    {
        if ($path) {
            $message = "Invalid image '{$path}': {$message}";
        }
        
        parent::__construct($message);
    }
}

