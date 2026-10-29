<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a rota aos perfis informados.
 * Uso: ->middleware('perfil:gerente') ou 'perfil:gerente,atendente'
 */
class VerificarPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->temPerfil(...$perfis)) {
            abort(403, 'Seu perfil não tem permissão para acessar esta funcionalidade.');
        }

        return $next($request);
    }
}
