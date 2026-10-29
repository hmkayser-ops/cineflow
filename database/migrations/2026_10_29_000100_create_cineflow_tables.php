<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do domínio do CineFlow contempladas na Sprint 1:
 * filmes, salas (com planta de assentos), tabela de preços,
 * sessões (com mapa de assentos da sessão) e auditoria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filmes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('titulo_original')->nullable();
            $table->text('sinopse')->nullable();
            $table->string('genero', 60);
            $table->unsignedSmallInteger('duracao_min');
            $table->string('classificacao', 2); // L, 10, 12, 14, 16, 18
            $table->string('distribuidora')->nullable();
            $table->date('licenca_inicio');
            $table->date('licenca_fim');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('salas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60)->unique();
            $table->unsignedTinyInteger('fileiras');
            $table->unsignedTinyInteger('assentos_por_fileira');
            $table->boolean('suporta_3d')->default(false);
            $table->string('status', 20)->default('ativa'); // ativa | manutencao | inativa
            $table->date('manutencao_ate')->nullable();
            $table->timestamps();
        });

        // Planta da sala: cada posição da grade é um assento
        Schema::create('assentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sala_id')->constrained('salas')->cascadeOnDelete();
            $table->string('fileira', 2);
            $table->unsignedTinyInteger('numero');
            $table->string('tipo', 20)->default('padrao'); // padrao | acessivel | inexistente (corredor)
            $table->unique(['sala_id', 'fileira', 'numero']);
        });

        Schema::create('tabelas_preco', function (Blueprint $table) {
            $table->id();
            $table->string('formato', 2); // 2D | 3D
            $table->decimal('valor_inteira', 8, 2);
            $table->date('vigente_desde');
            $table->timestamps();
        });

        Schema::create('sessoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filme_id')->constrained('filmes')->restrictOnDelete();
            $table->foreignId('sala_id')->constrained('salas')->restrictOnDelete();
            $table->dateTime('inicio');
            $table->dateTime('fim'); // inclui trailers e limpeza da sala
            $table->string('formato', 2); // 2D | 3D
            $table->string('idioma', 10); // dublado | legendado
            $table->decimal('valor_inteira', 8, 2);
            $table->decimal('valor_meia', 8, 2);
            $table->string('status', 20)->default('programada'); // programada | cancelada
            $table->foreignId('programada_por')->constrained('users');
            $table->timestamps();
            $table->index(['sala_id', 'inicio', 'fim']);
        });

        // Mapa de assentos da sessão, gerado a partir da planta da sala
        Schema::create('sessao_assentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sessao_id')->constrained('sessoes')->cascadeOnDelete();
            $table->foreignId('assento_id')->constrained('assentos')->cascadeOnDelete();
            $table->string('status', 20)->default('disponivel'); // disponivel | bloqueado | ocupado
            $table->unique(['sessao_id', 'assento_id']);
        });

        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 40);
            $table->string('entidade', 40);
            $table->unsignedBigInteger('entidade_id')->nullable();
            $table->json('detalhes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('sessao_assentos');
        Schema::dropIfExists('sessoes');
        Schema::dropIfExists('tabelas_preco');
        Schema::dropIfExists('assentos');
        Schema::dropIfExists('salas');
        Schema::dropIfExists('filmes');
    }
};
