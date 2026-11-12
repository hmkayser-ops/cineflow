"""Testes automatizados do CineFlow.

Rode com:  ./.venv/bin/python -m unittest discover -s testes -v
Cada teste usa um banco temporário próprio, então não mexe nos dados de
demonstração.
"""
import sys
import tempfile
import unittest
from datetime import date, datetime, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from modelos import Filme, Sala, Pagamento, SessaoAssento, Ingresso   # noqa: E402
from repositorio import Repositorio                                   # noqa: E402
from servicos import (ErroDeRegra, ServicoCompra, ServicoSessao,       # noqa: E402
                      MINUTOS_TRAILERS, MINUTOS_LIMPEZA)


class BaseComBanco(unittest.TestCase):
    def setUp(self):
        self.arquivo = Path(tempfile.mkdtemp()) / 'teste.db'
        self.repo = Repositorio(self.arquivo)
        self.repo.criar_tabelas()
        self.sessao_servico = ServicoSessao(self.repo)
        self.compra_servico = ServicoCompra(self.repo)

        hoje = date.today()
        self.filme = self.repo.buscar_filme(self.repo.salvar_filme(
            Filme(0, 'Filme de Teste', 'Drama', 100, '12',
                  hoje - timedelta(days=1), hoje + timedelta(days=30), True)))
        self.sala = self.repo.buscar_sala(self.repo.salvar_sala(Sala(0, 'Sala A', 2, 3, True)))
        self.amanha = datetime.combine(hoje + timedelta(days=1),
                                       datetime.min.time()).replace(hour=20)

    def programar(self, hora=None, preco=30.0):
        return self.sessao_servico.programar(self.filme, self.sala, hora or self.amanha,
                                             '2D', preco)


class TestModelos(BaseComBanco):
    def test_capacidade_da_sala_e_fileiras_vezes_assentos(self):
        self.assertEqual(self.sala.capacidade(), 6)

    def test_planta_gerada_com_letra_e_numero(self):
        assentos = self.repo.listar_assentos(self.sala.id)
        self.assertEqual(len(assentos), 6)
        self.assertEqual([a.codigo() for a in assentos],
                         ['A1', 'A2', 'A3', 'B1', 'B2', 'B3'])

    def test_meia_entrada_e_metade_da_inteira(self):
        sessao = self.programar(preco=31.00)
        self.assertEqual(sessao.preco_meia(), 15.50)

    def test_licenca_cobre_apenas_o_periodo(self):
        self.assertTrue(self.filme.licenca_cobre(date.today()))
        self.assertFalse(self.filme.licenca_cobre(date.today() + timedelta(days=60)))

    def test_codigo_do_ingresso_e_unico(self):
        codigos = {Ingresso.gerar_codigo() for _ in range(200)}
        self.assertEqual(len(codigos), 200)

    def test_assento_bloqueado_vencido_volta_a_ficar_disponivel(self):
        agora = datetime.now()
        vencido = SessaoAssento(1, 1, 1, 'bloqueado', agora - timedelta(minutes=1))
        valendo = SessaoAssento(2, 1, 2, 'bloqueado', agora + timedelta(minutes=5))
        self.assertTrue(vencido.disponivel(agora))
        self.assertFalse(valendo.disponivel(agora))


class TestProgramarSessao(BaseComBanco):
    def test_fim_soma_trailers_duracao_e_limpeza(self):
        sessao = self.programar()
        esperado = MINUTOS_TRAILERS + self.filme.duracao_min + MINUTOS_LIMPEZA
        self.assertEqual((sessao.fim - sessao.inicio).total_seconds() / 60, esperado)

    def test_mapa_de_assentos_e_criado_junto(self):
        sessao = self.programar()
        self.assertEqual(len(self.repo.mapa_da_sessao(sessao.id)), self.sala.capacidade())

    def test_conflito_de_horario_na_mesma_sala(self):
        self.programar()
        with self.assertRaises(ErroDeRegra) as e:
            self.programar(self.amanha + timedelta(minutes=30))
        self.assertIn('já tem sessão', str(e.exception))

    def test_sessao_logo_depois_da_outra_e_aceita(self):
        primeira = self.programar()
        segunda = self.programar(primeira.fim + timedelta(minutes=1))
        self.assertIsNotNone(segunda.id)

    def test_nao_programa_no_passado(self):
        with self.assertRaises(ErroDeRegra):
            self.programar(datetime.now() - timedelta(hours=1))

    def test_nao_programa_fora_da_licenca(self):
        with self.assertRaises(ErroDeRegra) as e:
            self.programar(self.amanha + timedelta(days=90))
        self.assertIn('licença', str(e.exception))

    def test_preco_precisa_ser_positivo(self):
        with self.assertRaises(ErroDeRegra):
            self.programar(preco=0)


class TestComprarIngresso(BaseComBanco):
    def setUp(self):
        super().setUp()
        self.sessao = self.programar(preco=30.00)
        self.mapa = self.repo.mapa_da_sessao(self.sessao.id)

    def test_compra_bloqueia_os_assentos_escolhidos(self):
        compra = self.compra_servico.abrir(self.sessao, [self.mapa[0].id], 'Maria')
        depois = self.repo.buscar_lugares([self.mapa[0].id])[0]
        self.assertEqual(compra.status, 'aberta')
        self.assertEqual(depois.status, 'bloqueado')
        self.assertIsNotNone(depois.bloqueado_ate)

    def test_total_inicial_e_a_soma_das_inteiras(self):
        compra = self.compra_servico.abrir(self.sessao, [m.id for m in self.mapa[:3]], 'Maria')
        self.assertEqual(compra.total, 90.00)

    def test_segundo_cliente_nao_pega_assento_ja_bloqueado(self):
        self.compra_servico.abrir(self.sessao, [self.mapa[0].id], 'Maria')
        with self.assertRaises(ErroDeRegra) as e:
            self.compra_servico.abrir(self.sessao, [self.mapa[0].id], 'João')
        self.assertIn('acabaram de ser tomados', str(e.exception))

    def test_compra_sem_assento_e_recusada(self):
        with self.assertRaises(ErroDeRegra):
            self.compra_servico.abrir(self.sessao, [], 'Maria')

    def test_compra_sem_nome_e_recusada(self):
        with self.assertRaises(ErroDeRegra):
            self.compra_servico.abrir(self.sessao, [self.mapa[0].id], '   ')

    def test_meia_entrada_reduz_o_total(self):
        compra = self.compra_servico.abrir(self.sessao, [m.id for m in self.mapa[:2]], 'Maria')
        ingressos = self.compra_servico.ingressos_da_compra(compra.id)
        total = self.compra_servico.definir_meias(compra, {ingressos[0][0].id})
        self.assertEqual(total, 45.00)

    def test_pagamento_aprovado_emite_ingressos_e_ocupa_assentos(self):
        compra = self.compra_servico.abrir(self.sessao, [self.mapa[0].id], 'Maria')
        pagamento = self.compra_servico.pagar(compra)
        depois = self.repo.buscar_lugares([self.mapa[0].id])[0]
        self.assertEqual(pagamento.status, 'aprovado')
        self.assertNotEqual(pagamento.transacao_id, '')
        self.assertEqual(depois.status, 'ocupado')
        self.assertEqual(self.compra_servico.buscar_compra(compra.id).status, 'paga')
        self.assertEqual(len(self.compra_servico.ingressos_da_compra(compra.id)), 1)

    def test_pagamento_recusado_nao_ocupa_assento(self):
        sessao = self.programar(self.amanha + timedelta(days=3), preco=10.99)
        mapa = self.repo.mapa_da_sessao(sessao.id)
        compra = self.compra_servico.abrir(sessao, [mapa[0].id], 'Maria')
        pagamento = self.compra_servico.pagar(compra)
        self.assertEqual(pagamento.status, 'recusado')
        self.assertEqual(self.repo.buscar_lugares([mapa[0].id])[0].status, 'bloqueado')
        self.assertEqual(self.compra_servico.buscar_compra(compra.id).status, 'aberta')

    def test_nao_paga_duas_vezes_a_mesma_compra(self):
        compra = self.compra_servico.abrir(self.sessao, [self.mapa[0].id], 'Maria')
        self.compra_servico.pagar(compra)
        with self.assertRaises(ErroDeRegra):
            self.compra_servico.pagar(self.compra_servico.buscar_compra(compra.id))

    def test_bloqueio_vencido_libera_o_assento(self):
        compra = self.compra_servico.abrir(self.sessao, [self.mapa[0].id], 'Maria')
        with self.repo.conectar() as con:
            con.execute("UPDATE sessao_assentos SET bloqueado_ate = ? WHERE id = ?",
                        ((datetime.now() - timedelta(minutes=1)).strftime('%Y-%m-%d %H:%M:%S'),
                         self.mapa[0].id))
        self.assertEqual(self.compra_servico.liberar_expirados(), 1)
        self.assertEqual(self.repo.buscar_lugares([self.mapa[0].id])[0].status, 'livre')
        del compra

    def test_assento_de_outra_sessao_e_recusado(self):
        outra = self.programar(self.amanha + timedelta(days=5))
        mapa_outra = self.repo.mapa_da_sessao(outra.id)
        with self.assertRaises(ErroDeRegra):
            self.compra_servico.abrir(self.sessao, [mapa_outra[0].id], 'Maria')


class TestGatewaySimulado(unittest.TestCase):
    def test_aprova_valor_comum(self):
        p = Pagamento(0, 1, 60.00, 'pendente', '')
        self.assertTrue(p.autorizar())
        self.assertEqual(p.status, 'aprovado')

    def test_recusa_valor_terminado_em_99_centavos(self):
        p = Pagamento(0, 1, 10.99, 'pendente', '')
        self.assertFalse(p.autorizar())
        self.assertEqual(p.status, 'recusado')
        self.assertEqual(p.transacao_id, '')


if __name__ == '__main__':
    unittest.main()
