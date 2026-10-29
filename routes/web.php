<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FilmeController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\SessaoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// RF01 – Autenticação
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'formLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.entrar');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    // Todos os perfis podem consultar a grade de sessões e o detalhe de uma sessão
    Route::get('/sessoes', [SessaoController::class, 'index'])->name('sessoes.index');

    // Funcionalidades exclusivas da gerência
    Route::middleware('perfil:gerente')->group(function () {
        // RF02 – Cadastrar filme
        Route::resource('filmes', FilmeController::class)->except('show')
            ->parameters(['filmes' => 'filme']);

        // RF03 – Cadastrar sala com planta de assentos
        Route::resource('salas', SalaController::class)->except('show')
            ->parameters(['salas' => 'sala']);
        Route::get('/salas/{sala}/planta', [SalaController::class, 'planta'])->name('salas.planta');
        Route::put('/salas/{sala}/planta', [SalaController::class, 'salvarPlanta'])->name('salas.planta.salvar');

        // RF04 – Programar sessão
        Route::get('/sessoes/nova', [SessaoController::class, 'create'])->name('sessoes.create');
        Route::post('/sessoes/verificar', [SessaoController::class, 'verificar'])->name('sessoes.verificar');
        Route::post('/sessoes', [SessaoController::class, 'store'])->name('sessoes.store');
        Route::post('/sessoes/{sessao}/cancelar', [SessaoController::class, 'cancelar'])->name('sessoes.cancelar');

        // Usuários e auditoria
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy'])
            ->parameters(['usuarios' => 'usuario']);
        Route::get('/auditoria', AuditoriaController::class)->name('auditoria');
    });

    Route::get('/sessoes/{sessao}', [SessaoController::class, 'show'])->whereNumber('sessao')->name('sessoes.show');
});
