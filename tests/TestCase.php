<?php

namespace Tests;

use App\Models\Filme;
use App\Models\Sala;
use App\Models\TabelaPreco;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function gerente(): User
    {
        return User::factory()->gerente()->create();
    }

    protected function atendente(): User
    {
        return User::factory()->create();
    }

    protected function filme(array $dados = []): Filme
    {
        return Filme::create($dados + [
            'titulo' => 'Filme Teste '.uniqid(),
            'genero' => 'Drama',
            'duracao_min' => 120,
            'classificacao' => '12',
            'licenca_inicio' => today()->subDays(5),
            'licenca_fim' => today()->addDays(30),
        ]);
    }

    protected function sala(array $dados = []): Sala
    {
        $sala = Sala::create($dados + [
            'nome' => 'S'.uniqid(),
            'fileiras' => 3,
            'assentos_por_fileira' => 4,
        ]);
        $sala->gerarPlanta();

        return $sala->fresh();
    }

    protected function tabelasDePreco(): void
    {
        TabelaPreco::create(['formato' => '2D', 'valor_inteira' => 30, 'vigente_desde' => today()->subYear()]);
        TabelaPreco::create(['formato' => '3D', 'valor_inteira' => 38, 'vigente_desde' => today()->subYear()]);
    }
}
