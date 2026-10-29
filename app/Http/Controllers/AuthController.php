<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** RF01 – Autenticação com perfis. */
class AuthController extends Controller
{
    public function formLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Proteção contra força bruta: 5 tentativas por minuto por e-mail + IP
        $chave = Str::lower($credenciais['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($chave, 5)) {
            $segundos = RateLimiter::availableIn($chave);
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas de acesso. Tente novamente em {$segundos} segundos.",
            ]);
        }

        if (! Auth::attempt($credenciais, $request->boolean('lembrar'))) {
            RateLimiter::hit($chave, 60);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        if (! Auth::user()->ativo) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Usuário desativado. Procure a gerência do cinema.']);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();
        Auditoria::registrar('login', 'usuario', Auth::id());

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auditoria::registrar('logout', 'usuario', Auth::id());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
