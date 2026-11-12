"""Acesso ao banco de dados.

Toda consulta e toda gravação passam por aqui. O resto do sistema trabalha
com os objetos de modelos.py e não escreve SQL.
"""
import sqlite3
from datetime import datetime, date
from pathlib import Path

from modelos import (Usuario, Filme, Sala, Assento, Sessao, SessaoAssento,
                     Compra, Ingresso, Pagamento)

ARQUIVO_BANCO = Path(__file__).parent / 'cineflow.db'

ESQUEMA = """
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    perfil TEXT NOT NULL,
    ativo INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS filmes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL,
    genero TEXT NOT NULL,
    duracao_min INTEGER NOT NULL,
    classificacao TEXT NOT NULL,
    licenca_inicio TEXT NOT NULL,
    licenca_fim TEXT NOT NULL,
    ativo INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS salas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL UNIQUE,
    fileiras INTEGER NOT NULL,
    assentos_por_fileira INTEGER NOT NULL,
    ativa INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS assentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sala_id INTEGER NOT NULL REFERENCES salas(id),
    fileira TEXT NOT NULL,
    numero INTEGER NOT NULL,
    UNIQUE (sala_id, fileira, numero)
);
CREATE TABLE IF NOT EXISTS sessoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filme_id INTEGER NOT NULL REFERENCES filmes(id),
    sala_id INTEGER NOT NULL REFERENCES salas(id),
    inicio TEXT NOT NULL,
    fim TEXT NOT NULL,
    formato TEXT NOT NULL,
    preco_inteira REAL NOT NULL,
    status TEXT NOT NULL DEFAULT 'programada'
);
CREATE TABLE IF NOT EXISTS sessao_assentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sessao_id INTEGER NOT NULL REFERENCES sessoes(id),
    assento_id INTEGER NOT NULL REFERENCES assentos(id),
    status TEXT NOT NULL DEFAULT 'livre',
    bloqueado_ate TEXT,
    UNIQUE (sessao_id, assento_id)
);
CREATE TABLE IF NOT EXISTS compras (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sessao_id INTEGER NOT NULL REFERENCES sessoes(id),
    cliente_nome TEXT NOT NULL,
    total REAL NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'aberta',
    criada_em TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS ingressos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    compra_id INTEGER NOT NULL REFERENCES compras(id),
    sessao_assento_id INTEGER NOT NULL REFERENCES sessao_assentos(id),
    tipo TEXT NOT NULL,
    valor REAL NOT NULL,
    codigo TEXT NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS pagamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    compra_id INTEGER NOT NULL REFERENCES compras(id),
    valor REAL NOT NULL,
    status TEXT NOT NULL,
    transacao_id TEXT NOT NULL DEFAULT ''
);
"""

FORMATO = '%Y-%m-%d %H:%M:%S'


def _dt(texto):
    return datetime.strptime(texto, FORMATO) if texto else None


class _Conexao(sqlite3.Connection):
    """Igual à conexão normal, mas o `with` também fecha ao sair.

    O sqlite3 só faz commit ou rollback no fim do `with`, e deixa a conexão
    aberta. Fechar junto evita acumular conexões.
    """

    def __exit__(self, *erro):
        try:
            super().__exit__(*erro)
        finally:
            self.close()


class Repositorio:
    def __init__(self, arquivo=ARQUIVO_BANCO):
        self.arquivo = str(arquivo)

    def conectar(self):
        con = sqlite3.connect(self.arquivo, factory=_Conexao)
        con.row_factory = sqlite3.Row
        con.execute('PRAGMA foreign_keys = ON')
        return con

    def criar_tabelas(self):
        with self.conectar() as con:
            con.executescript(ESQUEMA)

    # ---------------- usuários ----------------
    def buscar_usuario(self, email):
        with self.conectar() as con:
            l = con.execute('SELECT * FROM usuarios WHERE email = ?', (email,)).fetchone()
        return Usuario(l['id'], l['nome'], l['email'], l['senha_hash'],
                       l['perfil'], bool(l['ativo'])) if l else None

    # ---------------- filmes ----------------
    def listar_filmes(self, so_ativos=False):
        sql = 'SELECT * FROM filmes' + (' WHERE ativo = 1' if so_ativos else '') + ' ORDER BY titulo'
        with self.conectar() as con:
            linhas = con.execute(sql).fetchall()
        return [self._filme(l) for l in linhas]

    def buscar_filme(self, filme_id):
        with self.conectar() as con:
            l = con.execute('SELECT * FROM filmes WHERE id = ?', (filme_id,)).fetchone()
        return self._filme(l) if l else None

    def _filme(self, l):
        return Filme(l['id'], l['titulo'], l['genero'], l['duracao_min'], l['classificacao'],
                     date.fromisoformat(l['licenca_inicio']), date.fromisoformat(l['licenca_fim']),
                     bool(l['ativo']))

    def salvar_filme(self, f):
        with self.conectar() as con:
            if f.id:
                con.execute('UPDATE filmes SET titulo=?, genero=?, duracao_min=?, '
                            'classificacao=?, licenca_inicio=?, licenca_fim=?, ativo=? WHERE id=?',
                            (f.titulo, f.genero, f.duracao_min, f.classificacao,
                             f.licenca_inicio.isoformat(), f.licenca_fim.isoformat(),
                             int(f.ativo), f.id))
                return f.id
            cur = con.execute('INSERT INTO filmes (titulo, genero, duracao_min, classificacao, '
                              'licenca_inicio, licenca_fim, ativo) VALUES (?,?,?,?,?,?,?)',
                              (f.titulo, f.genero, f.duracao_min, f.classificacao,
                               f.licenca_inicio.isoformat(), f.licenca_fim.isoformat(),
                               int(f.ativo)))
            return cur.lastrowid

    def excluir_filme(self, filme_id):
        """Só exclui se o filme não tiver sessão. Senão, desativa."""
        with self.conectar() as con:
            tem = con.execute('SELECT 1 FROM sessoes WHERE filme_id = ?', (filme_id,)).fetchone()
            if tem:
                con.execute('UPDATE filmes SET ativo = 0 WHERE id = ?', (filme_id,))
                return False
            con.execute('DELETE FROM filmes WHERE id = ?', (filme_id,))
            return True

    # ---------------- salas ----------------
    def listar_salas(self, so_ativas=False):
        sql = 'SELECT * FROM salas' + (' WHERE ativa = 1' if so_ativas else '') + ' ORDER BY nome'
        with self.conectar() as con:
            linhas = con.execute(sql).fetchall()
        return [Sala(l['id'], l['nome'], l['fileiras'], l['assentos_por_fileira'],
                     bool(l['ativa'])) for l in linhas]

    def buscar_sala(self, sala_id):
        with self.conectar() as con:
            l = con.execute('SELECT * FROM salas WHERE id = ?', (sala_id,)).fetchone()
        return Sala(l['id'], l['nome'], l['fileiras'], l['assentos_por_fileira'],
                    bool(l['ativa'])) if l else None

    def salvar_sala(self, s):
        """Grava a sala e gera a planta de assentos (A1, A2... B1, B2...)."""
        with self.conectar() as con:
            cur = con.execute('INSERT INTO salas (nome, fileiras, assentos_por_fileira, ativa) '
                              'VALUES (?,?,?,?)',
                              (s.nome, s.fileiras, s.assentos_por_fileira, int(s.ativa)))
            sala_id = cur.lastrowid
            for i in range(s.fileiras):
                letra = chr(ord('A') + i)
                for n in range(1, s.assentos_por_fileira + 1):
                    con.execute('INSERT INTO assentos (sala_id, fileira, numero) VALUES (?,?,?)',
                                (sala_id, letra, n))
        return sala_id

    def listar_assentos(self, sala_id):
        with self.conectar() as con:
            linhas = con.execute('SELECT * FROM assentos WHERE sala_id = ? '
                                 'ORDER BY fileira, numero', (sala_id,)).fetchall()
        return [Assento(l['id'], l['sala_id'], l['fileira'], l['numero']) for l in linhas]

    # ---------------- sessões ----------------
    def listar_sessoes(self, dia=None, so_programadas=True):
        sql = ('SELECT s.*, f.titulo AS filme_titulo, sa.nome AS sala_nome '
               'FROM sessoes s JOIN filmes f ON f.id = s.filme_id '
               'JOIN salas sa ON sa.id = s.sala_id WHERE 1=1')
        par = []
        if so_programadas:
            sql += " AND s.status = 'programada'"
        if dia:
            sql += ' AND date(s.inicio) = ?'
            par.append(dia.isoformat())
        sql += ' ORDER BY s.inicio'
        with self.conectar() as con:
            linhas = con.execute(sql, par).fetchall()
        return [self._sessao(l) for l in linhas]

    def buscar_sessao(self, sessao_id):
        with self.conectar() as con:
            l = con.execute('SELECT s.*, f.titulo AS filme_titulo, sa.nome AS sala_nome '
                            'FROM sessoes s JOIN filmes f ON f.id = s.filme_id '
                            'JOIN salas sa ON sa.id = s.sala_id WHERE s.id = ?',
                            (sessao_id,)).fetchone()
        return self._sessao(l) if l else None

    def _sessao(self, l):
        return Sessao(l['id'], l['filme_id'], l['sala_id'], _dt(l['inicio']), _dt(l['fim']),
                      l['formato'], l['preco_inteira'], l['status'],
                      l['filme_titulo'], l['sala_nome'])

    def sessoes_da_sala(self, sala_id):
        with self.conectar() as con:
            linhas = con.execute("SELECT * FROM sessoes WHERE sala_id = ? AND "
                                 "status = 'programada'", (sala_id,)).fetchall()
        return [(_dt(l['inicio']), _dt(l['fim'])) for l in linhas]

    def salvar_sessao(self, s):
        """Grava a sessão e cria o mapa de assentos a partir da planta da sala."""
        with self.conectar() as con:
            cur = con.execute('INSERT INTO sessoes (filme_id, sala_id, inicio, fim, formato, '
                              'preco_inteira, status) VALUES (?,?,?,?,?,?,?)',
                              (s.filme_id, s.sala_id, s.inicio.strftime(FORMATO),
                               s.fim.strftime(FORMATO), s.formato, s.preco_inteira, s.status))
            sessao_id = cur.lastrowid
            con.execute('INSERT INTO sessao_assentos (sessao_id, assento_id) '
                        'SELECT ?, id FROM assentos WHERE sala_id = ?', (sessao_id, s.sala_id))
        return sessao_id

    # ---------------- mapa de assentos ----------------
    def mapa_da_sessao(self, sessao_id):
        with self.conectar() as con:
            linhas = con.execute(
                'SELECT sa.*, a.fileira, a.numero FROM sessao_assentos sa '
                'JOIN assentos a ON a.id = sa.assento_id WHERE sa.sessao_id = ? '
                'ORDER BY a.fileira, a.numero', (sessao_id,)).fetchall()
        return [SessaoAssento(l['id'], l['sessao_id'], l['assento_id'], l['status'],
                              _dt(l['bloqueado_ate']), f"{l['fileira']}{l['numero']}")
                for l in linhas]

    def buscar_lugares(self, ids):
        if not ids:
            return []
        marcas = ','.join('?' * len(ids))
        with self.conectar() as con:
            linhas = con.execute(
                f'SELECT sa.*, a.fileira, a.numero FROM sessao_assentos sa '
                f'JOIN assentos a ON a.id = sa.assento_id WHERE sa.id IN ({marcas})',
                ids).fetchall()
        return [SessaoAssento(l['id'], l['sessao_id'], l['assento_id'], l['status'],
                              _dt(l['bloqueado_ate']), f"{l['fileira']}{l['numero']}")
                for l in linhas]
