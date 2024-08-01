<?php

namespace App\Exceptions;

use Exception;

class CircularDependencyException extends Exception
{
    protected $message = 'Circular dependency detected.';
}
