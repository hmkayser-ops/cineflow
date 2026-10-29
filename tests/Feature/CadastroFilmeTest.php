<?php

namespace Tests\Feature;

use App\Models\Filme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF02 – Cadastrar filme */
class CadastroFilmeTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return $extra + [
            'titulo' => 'Filme Novo',
            'genero' => 'Comédia',
            'duracao_min' => 101,
            'classificacao' => '10',
            'licenca_inicio' => today()->toDateString(),
            'licenca_fim' => today()->addMonth()->toDateString(),
            'ativo' => '1',
        ];
    }

    public function test_gerente_cadastra_filme(): void
    {
        $this->actingAs($this->gerente())
            ->post('/filmes', $this->dados())
            ->assertRedirect('/filmes');

        $this->assertDatabaseHas('filmes', ['titulo' => 'Filme Novo', 'duracao_min' => 101]);
        $this->assertDatabaseHas('auditorias', ['acao' => 'criar', 'entidade' => 'filme']);
    }

    public function test_campos_obrigatorios(): void
    {
        $this->actingAs($this->gerente())
            ->post('/filmes', [])
            ->assertSessionHasErrors(['titulo', 'genero', 'duracao_min', 'classificacao', 'licenca_inicio', 'licenca_fim']);
    }

    public function test_fim_da_licenca_nao_pode_ser_antes_do_inicio(): void
    {
        $this->actingAs($this->gerente())
            ->post('/filmes', $this->dados(['licenca_fim' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('licenca_fim');
    }

    public function test_titulo_duplicado_e_recusado(): void
    {
        $this->filme(['titulo' => 'Repetido']);

        $this->actingAs($this->gerente())
            ->post('/filmes', $this->dados(['titulo' => 'Repetido']))
            ->assertSessionHasErrors('titulo');
    }

    public function test_gerente_edita_filme(): void
    {
        $f = $this->filme();

        $this->actingAs($this->gerente())
            ->put("/filmes/{$f->id}", $this->dados(['titulo' => 'Título Corrigido']))
            ->assertRedirect('/filmes');

        $this->assertSame('Título Corrigido', $f->fresh()->titulo);
    }

    public function test_filme_sem_sessoes_e_excluido(): void
    {
        $f = $this->filme();

        $this->actingAs($this->gerente())->delete("/filmes/{$f->id}");

        $this->assertDatabaseMissing('filmes', ['id' => $f->id]);
    }

    public function test_filme_com_sessoes_e_apenas_desativado(): void
    {
        $this->tabelasDePreco();
        $g = $this->gerente();
        $f = $this->filme();
        $s = $this->sala();
        app(\App\Services\ProgramacaoSessaoService::class)->programar([
            'filme_id' => $f->id, 'sala_id' => $s->id, 'inicio' => today()->addDay()->setTime(15, 0),
            'formato' => '2D', 'idioma' => 'dublado', 'valor_inteira' => 30, 'valor_meia' => 15,
        ], $g);

        $this->actingAs($g)->delete("/filmes/{$f->id}");

        $this->assertFalse(Filme::find($f->id)->ativo);
    }
}
