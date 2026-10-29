<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF01 – Autenticação com perfis */
class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/filmes')->assertRedirect('/login');
    }

    public function test_login_com_credenciais_validas(): void
    {
        $u = User::factory()->gerente()->create(['email' => 'g@cineflow.test']);

        $this->post('/login', ['email' => 'g@cineflow.test', 'password' => 'cineflow123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('auditorias', ['acao' => 'login', 'user_id' => $u->id]);
    }

    public function test_login_com_senha_errada_e_recusado(): void
    {
        User::factory()->create(['email' => 'a@cineflow.test']);

        $this->post('/login', ['email' => 'a@cineflow.test', 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_usuario_desativado_nao_entra(): void
    {
        User::factory()->inativo()->create(['email' => 'x@cineflow.test']);

        $this->post('/login', ['email' => 'x@cineflow.test', 'password' => 'cineflow123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_bloqueio_apos_cinco_tentativas(): void
    {
        User::factory()->create(['email' => 'b@cineflow.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'b@cineflow.test', 'password' => 'errada']);
        }

        $this->post('/login', ['email' => 'b@cineflow.test', 'password' => 'cineflow123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_senha_armazenada_com_hash(): void
    {
        $u = User::factory()->create();

        $this->assertNotSame('cineflow123', $u->getRawOriginal('password'));
        $this->assertTrue(password_verify('cineflow123', $u->getRawOriginal('password')));
    }

    public function test_atendente_nao_acessa_funcoes_da_gerencia(): void
    {
        $this->actingAs($this->atendente());

        $this->get('/filmes')->assertForbidden();
        $this->get('/salas')->assertForbidden();
        $this->get('/sessoes/nova')->assertForbidden();
        $this->get('/usuarios')->assertForbidden();
        $this->get('/sessoes')->assertOk(); // consulta permitida
    }

    public function test_portaria_nao_acessa_funcoes_da_gerencia(): void
    {
        $this->actingAs(User::factory()->portaria()->create());

        $this->get('/filmes')->assertForbidden();
        $this->post('/sessoes', [])->assertForbidden();
    }

    public function test_gerente_acessa_todas_as_telas(): void
    {
        $this->actingAs($this->gerente());

        foreach (['/', '/filmes', '/filmes/create', '/salas', '/salas/create', '/sessoes', '/sessoes/nova', '/usuarios', '/auditoria'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_logout(): void
    {
        $this->actingAs($this->gerente())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame(1, Auditoria::where('acao', 'logout')->count());
    }
}
