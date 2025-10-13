<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
protected function redirectTo($request): ?string
{
    if ($request->expectsJson() || $request->is('api/*')) {
        return null; // API => 401 JSON
    }
    return route('login'); // solo para rutas web
}

}
