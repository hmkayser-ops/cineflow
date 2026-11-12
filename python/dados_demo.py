"""Cria o banco do zero com dados de demonstração.

Rode com: python dados_demo.py
Apaga o cineflow.db anterior, então serve para voltar tudo ao estado inicial.
"""
from datetime import date, datetime, timedelta

from werkzeug.security import generate_password_hash

from modelos import Filme, Sala
from repositorio import Repositorio, ARQUIVO_BANCO
from servicos import ServicoSessao


def recriar():
    if ARQUIVO_BANCO.exists():
        ARQUIVO_BANCO.unlink()
    repo = Repositorio()
    repo.criar_tabelas()

    with repo.conectar() as con:
        for nome, email, perfil in [('Gerente do Cinema', 'gerente@cineflow.test', 'gerente'),
                                    ('Atendente de Bilheteria', 'atendente@cineflow.test',
                                     'atendente')]:
            con.execute('INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo) '
                        'VALUES (?,?,?,?,1)',
                        (nome, email, generate_password_hash('cineflow123'), perfil))

    hoje = date.today()
    filmes = [
        Filme(0, 'O Último Trem', 'Ação', 118, '14', hoje - timedelta(days=10),
              hoje + timedelta(days=60), True),
        Filme(0, 'Caminhos de Areia', 'Drama', 97, '12', hoje - timedelta(days=5),
              hoje + timedelta(days=45), True),
        Filme(0, 'A Casa no Morro', 'Terror', 105, '16', hoje, hoje + timedelta(days=30), True),
        Filme(0, 'Patas Valentes', 'Animação', 88, 'L', hoje - timedelta(days=20),
              hoje + timedelta(days=90), True),
    ]
    ids_filmes = [repo.salvar_filme(f) for f in filmes]

    salas = [Sala(0, 'Sala 1', 5, 8, True),
             Sala(0, 'Sala 2', 4, 6, True),
             Sala(0, 'Sala 3 (3D)', 6, 10, True)]
    ids_salas = [repo.salvar_sala(s) for s in salas]

    servico = ServicoSessao(repo)
    agenda = [(0, 0, 14, 30, '2D', 28.00), (1, 1, 16, 0, '2D', 24.00),
              (2, 2, 19, 0, '3D', 38.00), (3, 0, 18, 0, '2D', 28.00),
              (0, 2, 21, 30, '3D', 38.00)]
    criadas = 0
    for i_filme, i_sala, hora, minuto, formato, preco in agenda:
        filme = repo.buscar_filme(ids_filmes[i_filme])
        sala = repo.buscar_sala(ids_salas[i_sala])
        inicio = datetime.combine(hoje, datetime.min.time()).replace(hour=hora, minute=minuto)
        if inicio < datetime.now():
            inicio += timedelta(days=1)
        try:
            servico.programar(filme, sala, inicio, formato, preco)
            criadas += 1
        except Exception as e:
            print(f'  sessão não criada ({filme.titulo}): {e}')

    print(f'Banco recriado: {len(ids_filmes)} filmes, {len(ids_salas)} salas, '
          f'{criadas} sessões.')
    print('Usuários: gerente@cineflow.test e atendente@cineflow.test, senha cineflow123.')


if __name__ == '__main__':
    recriar()
