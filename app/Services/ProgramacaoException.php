<?php

namespace App\Services;

use RuntimeException;

/**
 * Violação de uma regra de negócio ao programar uma sessão.
 * Cada código corresponde a um fluxo alternativo da especificação
 * do caso de uso "Programar sessão".
 */
class ProgramacaoException extends RuntimeException
{
    public const CONFLITO_HORARIO = 'FA1';      // Conflito de horário na sala
    public const SEM_LICENCA = 'FA2';           // Filme sem licença vigente na data
    public const SALA_MANUTENCAO = 'FA3';       // Sala em manutenção
    public const DADOS_INVALIDOS = 'FA4';       // Dados obrigatórios inválidos
    public const SALA_SEM_3D = 'FA6';           // Sala não suporta o formato 3D
    public const SEM_TABELA_PRECO = 'RN-PRECO'; // Não existe tabela de preço vigente

    public function __construct(
        string $message,
        public readonly string $codigo,
        public readonly array $contexto = [],
    ) {
        parent::__construct($message);
    }
}
