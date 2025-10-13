<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * Cookies que NO deben encriptarse.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
