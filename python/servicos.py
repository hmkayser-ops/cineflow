"""Regras de negócio do CineFlow.

Fica aqui o que não cabe em uma classe de modelo sozinha: programar uma
sessão sem conflito de horário e conduzir a compra de ingressos.
"""
from datetime import datetime, timedelta

from modelos import Sessao, Compra, Ingresso, Pagamento
from repositorio import FORMATO

MINUTOS_TRAILERS = 15
MINUTOS_LIMPEZA = 20
MINUTOS_BLOQUEIO = 10


class ErroDeRegra(Exception):
    """Alguma regra de negócio barrou a operação. A mensagem vai para a tela."""


class ServicoSessao:
    def __init__(self, repo):
        self.repo = repo

    def calcular_fim(self, filme, inicio):
        """Término = início + trailers + duração do filme + limpeza da sala."""
        total = MINUTOS_TRAILERS + filme.duracao_min + MINUTOS_LIMPEZA
        return inicio + timedelta(minutes=total)

    def ha_conflito(self, sala_id, inicio, fim):
        """Duas sessões conflitam quando os períodos se sobrepõem."""
        for outro_inicio, outro_fim in self.repo.sessoes_da_sala(sala_id):
            if inicio < outro_fim and outro_inicio < fim:
                return True
        return False

    def programar(self, filme, sala, inicio, formato, preco_inteira):
        if inicio < datetime.now():
            raise ErroDeRegra('Não dá para programar uma sessão no passado.')
        if not filme.licenca_cobre(inicio.date()):
            raise ErroDeRegra(f'A licença de "{filme.titulo}" não cobre essa data.')
        if not sala.ativa:
            raise ErroDeRegra(f'A sala {sala.nome} está inativa.')
        if preco_inteira <= 0:
            raise ErroDeRegra('O preço da inteira precisa ser maior que zero.')

        fim = self.calcular_fim(filme, inicio)

        # A verificação de conflito e a gravação acontecem na mesma transação.
        # Só conferir antes de gravar não bastaria: entre uma coisa e outra,
        # outro gerente poderia ocupar o mesmo horário.
        con = self.repo.conectar()
        try:
            con.execute('BEGIN IMMEDIATE')
            ocupadas = con.execute(
                "SELECT inicio, fim FROM sessoes WHERE sala_id = ? AND status = 'programada'",
                (sala.id,)).fetchall()
            for linha in ocupadas:
                outro_inicio = datetime.strptime(linha['inicio'], FORMATO)
                outro_fim = datetime.strptime(linha['fim'], FORMATO)
                if inicio < outro_fim and outro_inicio < fim:
                    raise ErroDeRegra(f'A sala {sala.nome} já tem sessão nesse horário '
                                      f'({inicio:%H:%M} às {fim:%H:%M}).')

            cur = con.execute('INSERT INTO sessoes (filme_id, sala_id, inicio, fim, formato, '
                              'preco_inteira, status) VALUES (?,?,?,?,?,?,?)',
                              (filme.id, sala.id, inicio.strftime(FORMATO),
                               fim.strftime(FORMATO), formato, preco_inteira, 'programada'))
            sessao_id = cur.lastrowid
            con.execute('INSERT INTO sessao_assentos (sessao_id, assento_id) '
                        'SELECT ?, id FROM assentos WHERE sala_id = ?', (sessao_id, sala.id))
            con.commit()
        except Exception:
            con.rollback()
            raise
        finally:
            con.close()

        return Sessao(sessao_id, filme.id, sala.id, inicio, fim, formato, preco_inteira,
                      'programada')


class ServicoCompra:
    def __init__(self, repo):
        self.repo = repo

    def abrir(self, sessao, ids_lugares, cliente_nome):
        """Bloqueia os assentos por 10 minutos e abre a compra.

        A verificação e o bloqueio acontecem na mesma transação, com BEGIN
        IMMEDIATE, para que duas compras ao mesmo tempo não peguem o mesmo
        assento.
        """
        if not ids_lugares:
            raise ErroDeRegra('Escolha pelo menos um assento.')
        if not cliente_nome.strip():
            raise ErroDeRegra('Informe o nome do cliente.')

        agora = datetime.now()
        limite = agora + timedelta(minutes=MINUTOS_BLOQUEIO)
        con = self.repo.conectar()
        try:
            con.execute('BEGIN IMMEDIATE')
            marcas = ','.join('?' * len(ids_lugares))
            linhas = con.execute(
                f'SELECT sa.id, sa.status, sa.bloqueado_ate, a.fileira, a.numero '
                f'FROM sessao_assentos sa JOIN assentos a ON a.id = sa.assento_id '
                f'WHERE sa.id IN ({marcas}) AND sa.sessao_id = ?',
                [*ids_lugares, sessao.id]).fetchall()

            if len(linhas) != len(ids_lugares):
                raise ErroDeRegra('Assento inválido para esta sessão.')

            tomados = []
            for l in linhas:
                vencido = (l['bloqueado_ate'] and
                           datetime.strptime(l['bloqueado_ate'], FORMATO) < agora)
                livre = l['status'] == 'livre' or (l['status'] == 'bloqueado' and vencido)
                if not livre:
                    tomados.append(f"{l['fileira']}{l['numero']}")
            if tomados:
                raise ErroDeRegra('Estes assentos acabaram de ser tomados: '
                                  + ', '.join(sorted(tomados)) + '. Escolha outros.')

            con.executemany(
                "UPDATE sessao_assentos SET status = 'bloqueado', bloqueado_ate = ? WHERE id = ?",
                [(limite.strftime(FORMATO), i) for i in ids_lugares])

            cur = con.execute('INSERT INTO compras (sessao_id, cliente_nome, total, status, '
                              'criada_em) VALUES (?,?,?,?,?)',
                              (sessao.id, cliente_nome.strip(), 0.0, 'aberta',
                               agora.strftime(FORMATO)))
            compra_id = cur.lastrowid

            # um ingresso por assento, inteira por padrão
            for i in ids_lugares:
                con.execute('INSERT INTO ingressos (compra_id, sessao_assento_id, tipo, valor, '
                            'codigo) VALUES (?,?,?,?,?)',
                            (compra_id, i, 'inteira', sessao.preco_inteira,
                             Ingresso.gerar_codigo()))
            total = round(sessao.preco_inteira * len(ids_lugares), 2)
            con.execute('UPDATE compras SET total = ? WHERE id = ?', (total, compra_id))
            con.commit()
        except Exception:
            con.rollback()
            raise
        finally:
            con.close()

        return Compra(compra_id, sessao.id, cliente_nome.strip(), total, 'aberta', agora)

    def definir_meias(self, compra, ids_meia):
        """Marca quais ingressos são meia-entrada e recalcula o total."""
        sessao = self.repo.buscar_sessao(compra.sessao_id)
        with self.repo.conectar() as con:
            total = 0.0
            for l in con.execute('SELECT id FROM ingressos WHERE compra_id = ?',
                                 (compra.id,)).fetchall():
                meia = l['id'] in ids_meia
                valor = sessao.preco_meia() if meia else sessao.preco_inteira
                con.execute('UPDATE ingressos SET tipo = ?, valor = ? WHERE id = ?',
                            ('meia' if meia else 'inteira', valor, l['id']))
                total += valor
            total = round(total, 2)
            con.execute('UPDATE compras SET total = ? WHERE id = ?', (total, compra.id))
        compra.total = total
        return total

    def pagar(self, compra):
        """Registra a tentativa, chama o gateway e, se aprovar, emite tudo."""
        if compra.status != 'aberta':
            raise ErroDeRegra('Esta compra já foi finalizada.')

        pagamento = Pagamento(0, compra.id, compra.total, 'pendente', '')
        with self.repo.conectar() as con:
            cur = con.execute('INSERT INTO pagamentos (compra_id, valor, status, transacao_id) '
                              'VALUES (?,?,?,?)', (compra.id, compra.total, 'pendente', ''))
            pagamento.id = cur.lastrowid

        aprovado = pagamento.autorizar()

        con = self.repo.conectar()
        try:
            con.execute('BEGIN IMMEDIATE')
            con.execute('UPDATE pagamentos SET status = ?, transacao_id = ? WHERE id = ?',
                        (pagamento.status, pagamento.transacao_id, pagamento.id))
            if aprovado:
                con.execute("UPDATE sessao_assentos SET status = 'ocupado', bloqueado_ate = NULL "
                            'WHERE id IN (SELECT sessao_assento_id FROM ingressos '
                            'WHERE compra_id = ?)', (compra.id,))
                con.execute("UPDATE compras SET status = 'paga' WHERE id = ?", (compra.id,))
                compra.status = 'paga'
            con.commit()
        except Exception:
            con.rollback()
            raise
        finally:
            con.close()
        return pagamento

    def liberar_expirados(self):
        """Devolve ao mapa os assentos cujo bloqueio venceu sem pagamento."""
        agora = datetime.now().strftime(FORMATO)
        with self.repo.conectar() as con:
            cur = con.execute("UPDATE sessao_assentos SET status = 'livre', bloqueado_ate = NULL "
                              "WHERE status = 'bloqueado' AND bloqueado_ate < ?", (agora,))
            liberados = cur.rowcount
            con.execute("UPDATE compras SET status = 'cancelada' WHERE status = 'aberta' "
                        "AND criada_em < datetime(?, '-10 minutes')", (agora,))
        return liberados

    def ingressos_da_compra(self, compra_id):
        with self.repo.conectar() as con:
            linhas = con.execute(
                'SELECT i.*, a.fileira, a.numero FROM ingressos i '
                'JOIN sessao_assentos sa ON sa.id = i.sessao_assento_id '
                'JOIN assentos a ON a.id = sa.assento_id '
                'WHERE i.compra_id = ? ORDER BY a.fileira, a.numero', (compra_id,)).fetchall()
        return [(Ingresso(l['id'], l['compra_id'], l['sessao_assento_id'], l['tipo'],
                          l['valor'], l['codigo']), f"{l['fileira']}{l['numero']}")
                for l in linhas]

    def buscar_compra(self, compra_id):
        from modelos import Compra as C
        from repositorio import _dt
        with self.repo.conectar() as con:
            l = con.execute('SELECT * FROM compras WHERE id = ?', (compra_id,)).fetchone()
        return C(l['id'], l['sessao_id'], l['cliente_nome'], l['total'], l['status'],
                 _dt(l['criada_em'])) if l else None
