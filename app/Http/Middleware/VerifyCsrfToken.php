<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs excluidas de verificación CSRF.
     *
     * @var array<int, string>
     */
    protected $except = [
        // '/webhook/*'
    ];
}
