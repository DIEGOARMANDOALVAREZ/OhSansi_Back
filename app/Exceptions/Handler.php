<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        //
    }

    /**
     * Respuesta 401 consistente para API.
     */
    protected function unauthenticated($request, AuthenticationException $e)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        return redirect()->guest(route('login'));
    }

    /**
     * Para cualquier excepción en /api/* devuelve JSON.
     * En debug muestra message + file:line + un trozo del trace.
     */
    public function render($request, Throwable $e)
    {
        if ($request->is('api/*')) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $payload = [
                'message' => $e->getMessage() ?: 'Server Error',
            ];

            if (config('app.debug')) {
                $payload['exception'] = class_basename($e);
                $payload['file'] = $e->getFile() . ':' . $e->getLine();
                $payload['trace'] = collect($e->getTrace())->take(5);
            }

            return response()->json($payload, $status);
        }

        return parent::render($request, $e);
    }
}
