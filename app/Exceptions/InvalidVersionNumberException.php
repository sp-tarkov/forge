<?php

namespace App\Exceptions;

use Exception;

class InvalidVersionNumberException extends Exception
{
    protected $message = 'The version number is an invalid semantic version.';
}
