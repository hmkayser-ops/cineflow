<?php

namespace Tests\Feature;

use App\Models\Assento;
use App\Models\Sala;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF03 – Cadastrar sala com planta de assentos */
class CadastroSalaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastro_gera_planta_de_assentos(): void
    {
        $this->actingAs($this->gerente())
            ->post('/salas', ['nome' => '5', 'fileiras' => 5, 'assentos_por_fileira' => 10, 'status' => 'ativa'])
            ->assertRedirect();

        $sala = Sala::where('nome', '5')->first();
        $this->assertSame(50, $sala->assentos()->count());
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $sala->assentos()->distinct()->orderBy('fileira')->pluck('fileira')->all());
    }

    public function test_nome_de_sala_unico(): void
    {
        $this->sala(['nome' => '1']);

        $this->actingAs($this->gerente())
            ->post('/salas', ['nome' => '1', 'fileiras' => 2, 'assentos_por_fileira' => 2, 'status' => 'ativa'])
            ->assertSessionHasErrors('nome');
    }

    public function test_limites_da_grade(): void
    {
        $this->actingAs($this->gerente())
            ->post('/salas', ['nome' => 'X', 'fileiras' => 30, 'assentos_por_fileira' => 0, 'status' => 'ativa'])
            ->assertSessionHasErrors(['fileiras', 'assentos_por_fileira']);
    }

    public function test_editor_de_planta_marca_corredor_e_acessibilidade(): void
    {
        $sala = $this->sala(); // 3 x 4 = 12
        $a = $sala->assentos()->get();
        $tipos = $a->pluck('tipo', 'id')->all();
        $tipos[$a[0]->id] = Assento::TIPO_ACESSIVEL;
        $tipos[$a[1]->id] = Assento::TIPO_INEXISTENTE;

        $this->actingAs($this->gerente())
            ->put("/salas/{$sala->id}/planta", ['tipos' => $tipos])
            ->assertRedirect('/salas');

        $this->assertSame(11, $sala->fresh()->capacidade);
        $this->assertSame(Assento::TIPO_ACESSIVEL, $a[0]->fresh()->tipo);
    }

    public function test_planta_nao_pode_ficar_sem_assentos(): void
    {
        $sala = $this->sala();
        $tipos = $sala->assentos()->pluck('id')->mapWithKeys(fn ($id) => [$id => Assento::TIPO_INEXISTENTE])->all();

        $this->actingAs($this->gerente())
            ->put("/salas/{$sala->id}/planta", ['tipos' => $tipos])
            ->assertSessionHasErrors('planta');

        $this->assertSame(12, $sala->fresh()->capacidade);
    }

    public function test_alterar_grade_preserva_tipos_existentes(): void
    {
        $sala = $this->sala();
        $sala->assentos()->where('fileira', 'A')->where('numero', 1)->update(['tipo' => Assento::TIPO_ACESSIVEL]);

        $this->actingAs($this->gerente())
            ->put("/salas/{$sala->id}", ['nome' => $sala->nome, 'fileiras' => 4, 'assentos_por_fileira' => 4, 'status' => 'ativa'])
            ->assertRedirect('/salas');

        $this->assertSame(16, $sala->assentos()->count());
        $this->assertSame(Assento::TIPO_ACESSIVEL, $sala->assentos()->where('fileira', 'A')->where('numero', 1)->value('tipo'));
    }
}
