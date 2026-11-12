"""Classes do domínio do CineFlow.

Cada classe aqui corresponde a uma do diagrama de classes e a uma tabela do
banco. São classes de dados simples: guardam os campos e as poucas regras que
dependem só do próprio objeto.
"""
from dataclasses import dataclass
from datetime import datetime, date
import secrets

from werkzeug.security import check_password_hash


@dataclass
class Usuario:
    id: int
    nome: str
    email: str
    senha_hash: str
    perfil: str          # gerente | atendente
    ativo: bool

    def verificar_senha(self, senha: str) -> bool:
        return check_password_hash(self.senha_hash, senha)

    def eh_gerente(self) -> bool:
        return self.perfil == 'gerente'


@dataclass
class Filme:
    id: int
    titulo: str
    genero: str
    duracao_min: int
    classificacao: str
    licenca_inicio: date
    licenca_fim: date
    ativo: bool

    def licenca_cobre(self, dia: date) -> bool:
        """O filme só pode ser programado dentro do período de licença."""
        return self.licenca_inicio <= dia <= self.licenca_fim


@dataclass
class Sala:
    id: int
    nome: str
    fileiras: int
    assentos_por_fileira: int
    ativa: bool

    def capacidade(self) -> int:
        return self.fileiras * self.assentos_por_fileira


@dataclass
class Assento:
    id: int
    sala_id: int
    fileira: str
    numero: int

    def codigo(self) -> str:
        return f'{self.fileira}{self.numero}'


@dataclass
class Sessao:
    id: int
    filme_id: int
    sala_id: int
    inicio: datetime
    fim: datetime
    formato: str         # 2D | 3D
    preco_inteira: float
    status: str          # programada | cancelada
    # preenchidos pelo repositório, para a tela não precisar buscar de novo
    filme_titulo: str = ''
    sala_nome: str = ''

    def preco_meia(self) -> float:
        """Meia-entrada é metade da inteira (Lei 12.933/2013)."""
        return round(self.preco_inteira / 2, 2)


@dataclass
class SessaoAssento:
    id: int
    sessao_id: int
    assento_id: int
    status: str                      # livre | bloqueado | ocupado
    bloqueado_ate: datetime | None
    codigo: str = ''                 # vem do assento, para desenhar o mapa

    def disponivel(self, agora: datetime) -> bool:
        """Livre, ou bloqueado por alguém que deixou o prazo vencer."""
        if self.status == 'livre':
            return True
        if self.status == 'bloqueado' and self.bloqueado_ate and self.bloqueado_ate < agora:
            return True
        return False


@dataclass
class Compra:
    id: int
    sessao_id: int
    cliente_nome: str
    total: float
    status: str          # aberta | paga | cancelada
    criada_em: datetime


@dataclass
class Ingresso:
    id: int
    compra_id: int
    sessao_assento_id: int
    tipo: str            # inteira | meia
    valor: float
    codigo: str

    @staticmethod
    def gerar_codigo() -> str:
        """Código único do ingresso, conferido na entrada da sala."""
        return secrets.token_hex(4).upper()


@dataclass
class Pagamento:
    id: int
    compra_id: int
    valor: float
    status: str          # pendente | aprovado | recusado
    transacao_id: str

    def autorizar(self) -> bool:
        """Gateway simulado: aprova, menos quando o valor termina em centavos
        .99, que usamos para demonstrar a recusa."""
        aprovado = round(self.valor % 1, 2) != 0.99
        self.status = 'aprovado' if aprovado else 'recusado'
        self.transacao_id = secrets.token_hex(6).upper() if aprovado else ''
        return aprovado
