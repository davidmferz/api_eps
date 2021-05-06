<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HashHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $strHash = env('SECRET_KEY_STR_TOKEN', null);

        if ($request->headers->get('secret-key') === $strHash) {
            return $next($request);
        } else {
            return response()->json(['mensaje' => 'Autorización inválida', 'code' => 401, 'strHash' => $strHash], 401);
        }
    }
}
