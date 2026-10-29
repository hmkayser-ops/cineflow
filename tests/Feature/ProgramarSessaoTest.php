<?php

namespace Tests\Feature;

use App\Models\Sessao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RF04 – Programar sessão.
 * Cobre o fluxo básico e os fluxos alternativos FA1 a FA4 da especificação.
 */
class ProgramarSessaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tabelasDePreco();
    }

    private function dados($filme, $sala, array $extra = []): array
    {
        return $extra + [
            'filme_id' => $filme->id,
            'sala_id' => $sala->id,
            'data' => today()->addDay()->toDateString(),
            'hora' => '19:00',
            'formato' => '2D',
            'idioma' => 'legendado',
            'valor_inteira' => '30.00',
            'valor_meia' => '15.00',
        ];
    }

    public function test_fluxo_basico_programa_sessao_e_gera_mapa_de_assentos(): void
    {
        $filme = $this->filme(['duracao_min' => 120]);
        $sala = $this->sala(); // 12 assentos

        $this->actingAs($this->gerente())
            ->post('/sessoes', $this->dados($filme, $sala))
            ->assertRedirect();

        $sessao = Sessao::first();
        // término = 19:00 + 15 (trailers) + 120 (filme) + 20 (limpeza) = 21:35
        $this->assertSame('21:35', $sessao->fim->format('H:i'));
        $this->assertSame('programada', $sessao->status);
        $this->assertSame(12, $sessao->assentos()->where('status', 'disponivel')->count());
        $this->assertDatabaseHas('auditorias', ['acao' => 'programar', 'entidade' => 'sessao', 'entidade_id' => $sessao->id]);
    }

    public function test_fa1_conflito_de_horario_na_sala(): void
    {
        $g = $this->gerente();
        $filme = $this->filme(['duracao_min' => 120]);
        $sala = $this->sala();
        $this->actingAs($g)->post('/sessoes', $this->dados($filme, $sala)); // 19:00–21:35

        $this->actingAs($g)
            ->post('/sessoes', $this->dados($filme, $sala, ['hora' => '21:00']))
            ->assertSessionHas('erro_regra', fn ($e) => $e['codigo'] === 'FA1' && str_contains($e['mensagem'], '21:35'));

        $this->assertSame(1, Sessao::count());
    }

    public function test_fa1_salas_diferentes_no_mesmo_horario_nao_conflitam(): void
    {
        $g = $this->gerente();
        $filme = $this->filme();
        $this->actingAs($g)->post('/sessoes', $this->dados($filme, $this->sala()));
        $this->actingAs($g)->post('/sessoes', $this->dados($filme, $this->sala()));

        $this->assertSame(2, Sessao::count());
    }

    public function test_fa1_sessao_cancelada_libera_o_horario(): void
    {
        $g = $this->gerente();
        $filme = $this->filme();
        $sala = $this->sala();
        $this->actingAs($g)->post('/sessoes', $this->dados($filme, $sala));
        $this->actingAs($g)->post('/sessoes/'.Sessao::first()->id.'/cancelar', ['motivo' => 'Falha no projetor']);

        $this->actingAs($g)->post('/sessoes', $this->dados($filme, $sala));

        $this->assertSame(1, Sessao::where('status', 'programada')->count());
        $this->assertDatabaseHas('auditorias', ['acao' => 'cancelar', 'entidade' => 'sessao']);
    }

    public function test_fa2_filme_sem_licenca_na_data(): void
    {
        $filme = $this->filme(['licenca_inicio' => today()->addDays(10), 'licenca_fim' => today()->addDays(20)]);

        $this->actingAs($this->gerente())
            ->post('/sessoes', $this->dados($filme, $this->sala()))
            ->assertSessionHas('erro_regra', fn ($e) => $e['codigo'] === 'FA2');

        $this->assertSame(0, Sessao::count());
    }

    public function test_fa3_sala_em_manutencao(): void
    {
        $sala = $this->sala(['status' => 'manutencao', 'manutencao_ate' => today()->addDays(3)]);

        $this->actingAs($this->gerente())
            ->post('/sessoes', $this->dados($this->filme(), $sala))
            ->assertSessionHas('erro_regra', fn ($e) => $e['codigo'] === 'FA3');
    }

    public function test_fa3_sala_liberada_apos_previsao_de_manutencao(): void
    {
        $sala = $this->sala(['status' => 'manutencao', 'manutencao_ate' => today()->addDays(2)]);

        $this->actingAs($this->gerente())
            ->post('/sessoes', $this->dados($this->filme(), $sala, ['data' => today()->addDays(3)->toDateString()]));

        $this->assertSame(1, Sessao::count());
    }

    public function test_fa4_data_no_passado(): void
    {
        $this->actingAs($this->gerente())
            ->post('/sessoes', $this->dados($this->filme(), $this->sala(), ['data' => today()->subDay()->toDateString()]))
            ->assertSessionHas('erro_regra', fn ($e) => $e['codigo'] === 'FA4');
    }

    public function test_fa4_campos_obrigatorios_preservando_o_digitado(): void
    {
        $this->actingAs($this->gerente())
            ->from('/sessoes/nova')
            ->post('/sessoes', ['hora' => '20:00'])
            ->assertRedirect('/sessoes/nova')
            ->assertSessionHasErrors(['filme_id', 'sala_id', 'data', 'valor_inteira'])
            ->assertSessionHasInput('hora', '20:00');
    }

    public function test_meia_maior_que_inteira_e_recusada(): void
    {
        $this->actingAs($this->gerente())
            ->post('/sessoes', $this->dados($this->filme(), $this->sala(), ['valor_meia' => '40']))
            ->assertSessionHasErrors('valor_meia');
    }

    public function test_fa6_sessao_3d_exige_sala_com_3d(): void
    {
        $this->actingAs($this->gerente())
            ->post('/sessoes', $this->dados($this->filme(), $this->sala(['suporta_3d' => false]), ['formato' => '3D']))
            ->assertSessionHas('erro_regra', fn ($e) => $e['codigo'] === 'FA6');
    }

    public function test_verificar_disponibilidade_retorna_termino_e_preco_padrao(): void
    {
        $this->actingAs($this->gerente())
            ->postJson('/sessoes/verificar', [
                'filme_id' => $this->filme(['duracao_min' => 100])->id,
                'sala_id' => $this->sala(['suporta_3d' => true])->id,
                'data' => today()->addDay()->toDateString(),
                'hora' => '18:00',
                'formato' => '3D',
            ])
            ->assertOk()
            ->assertJson(['disponivel' => true, 'termino' => '20:15', 'valor_inteira' => '38.00', 'valor_meia' => '19.00']);
    }

    public function test_verificar_disponibilidade_sugere_primeiro_horario_livre(): void
    {
        $g = $this->gerente();
        $filme = $this->filme(['duracao_min' => 120]);
        $sala = $this->sala();
        $this->actingAs($g)->post('/sessoes', $this->dados($filme, $sala)); // 19:00–21:35

        $this->actingAs($g)
            ->postJson('/sessoes/verificar', [
                'filme_id' => $filme->id, 'sala_id' => $sala->id,
                'data' => today()->addDay()->toDateString(), 'hora' => '20:00', 'formato' => '2D',
            ])
            ->assertJson(['disponivel' => false, 'codigo' => 'FA1', 'sugestao' => '21:35']);
    }

    public function test_programacao_em_lote_replica_e_informa_dias_recusados(): void
    {
        $g = $this->gerente();
        $filme = $this->filme(['duracao_min' => 90]);
        $sala = $this->sala();
        // ocupa a sala no 3º dia, no mesmo horário
        $this->actingAs($g)->post('/sessoes', $this->dados($filme, $sala, ['data' => today()->addDays(3)->toDateString()]));

        $this->actingAs($g)
            ->post('/sessoes', $this->dados($filme, $sala, ['replicar_dias' => 3]))
            ->assertSessionHas('recusadas', fn ($r) => count($r) === 1);

        $this->assertSame(4, Sessao::count()); // 1 anterior + 3 novas
    }

    public function test_atendente_nao_programa_sessao(): void
    {
        $this->actingAs($this->atendente())
            ->post('/sessoes', $this->dados($this->filme(), $this->sala()))
            ->assertForbidden();
    }
}
