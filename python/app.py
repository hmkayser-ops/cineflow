"""CineFlow – sistema de gestão de cinema.

Camada web: cada rota recebe o pedido, chama um serviço ou o repositório e
devolve uma tela. As regras de negócio ficam em servicos.py.
"""
from datetime import date, datetime, timedelta
from functools import wraps

from flask import (Flask, flash, redirect, render_template, request, session, url_for)

from modelos import Filme, Sala
from repositorio import Repositorio
from servicos import ErroDeRegra, ServicoCompra, ServicoSessao, MINUTOS_BLOQUEIO

app = Flask(__name__)
app.secret_key = 'cineflow-trabalho-les'

repo = Repositorio()
servico_sessao = ServicoSessao(repo)
servico_compra = ServicoCompra(repo)


def exige_gerente(f):
    """Só o perfil de gerência abre os cadastros."""
    @wraps(f)
    def interna(*a, **kw):
        if not session.get('usuario_id'):
            flash('Faça login para continuar.')
            return redirect(url_for('login'))
        if session.get('perfil') != 'gerente':
            flash('Esta área é só da gerência.')
            return redirect(url_for('programacao'))
        return f(*a, **kw)
    return interna


@app.context_processor
def dados_do_topo():
    return {'usuario_nome': session.get('nome'), 'perfil': session.get('perfil')}


# ---------------------------------------------------------------- autenticação
@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        usuario = repo.buscar_usuario(request.form.get('email', '').strip())
        if usuario and usuario.ativo and usuario.verificar_senha(request.form.get('senha', '')):
            session['usuario_id'] = usuario.id
            session['nome'] = usuario.nome
            session['perfil'] = usuario.perfil
            return redirect(url_for('programacao'))
        flash('E-mail ou senha incorretos.')
    return render_template('login.html')


@app.route('/sair')
def sair():
    session.clear()
    return redirect(url_for('programacao'))


# ---------------------------------------------------------------- RF05
@app.route('/')
def programacao():
    servico_compra.liberar_expirados()
    dia = date.fromisoformat(request.args.get('dia') or date.today().isoformat())
    sessoes = repo.listar_sessoes(dia)
    livres = {}
    for s in sessoes:
        mapa = repo.mapa_da_sessao(s.id)
        livres[s.id] = sum(1 for l in mapa if l.disponivel(datetime.now()))
    return render_template('programacao.html', sessoes=sessoes, dia=dia, livres=livres,
                           ontem=dia - timedelta(days=1), amanha=dia + timedelta(days=1))


# ---------------------------------------------------------------- RF02
@app.route('/filmes')
@exige_gerente
def filmes():
    return render_template('filmes.html', filmes=repo.listar_filmes())


@app.route('/filmes/novo', methods=['POST'])
@exige_gerente
def filme_novo():
    f = request.form
    try:
        inicio = date.fromisoformat(f['licenca_inicio'])
        fim = date.fromisoformat(f['licenca_fim'])
        if fim < inicio:
            raise ErroDeRegra('A data de fim da licença é anterior à de início.')
        if not f['titulo'].strip():
            raise ErroDeRegra('Informe o título do filme.')
        if int(f['duracao_min']) <= 0:
            raise ErroDeRegra('A duração precisa ser maior que zero.')
        repo.salvar_filme(Filme(0, f['titulo'].strip(), f['genero'], int(f['duracao_min']),
                                f['classificacao'], inicio, fim, True))
        flash(f'Filme "{f["titulo"]}" cadastrado.')
    except (ErroDeRegra, ValueError, KeyError) as e:
        flash(f'Não deu para cadastrar: {e}')
    return redirect(url_for('filmes'))


@app.route('/filmes/<int:filme_id>/excluir', methods=['POST'])
@exige_gerente
def filme_excluir(filme_id):
    flash('Filme excluído.' if repo.excluir_filme(filme_id)
          else 'O filme tem sessões, então foi apenas desativado.')
    return redirect(url_for('filmes'))


# ---------------------------------------------------------------- RF03
@app.route('/salas')
@exige_gerente
def salas():
    lista = repo.listar_salas()
    plantas = {s.id: repo.listar_assentos(s.id) for s in lista}
    return render_template('salas.html', salas=lista, plantas=plantas)


@app.route('/salas/nova', methods=['POST'])
@exige_gerente
def sala_nova():
    f = request.form
    try:
        fileiras, por_fileira = int(f['fileiras']), int(f['assentos_por_fileira'])
        if not 1 <= fileiras <= 26:
            raise ErroDeRegra('O número de fileiras precisa ficar entre 1 e 26.')
        if not 1 <= por_fileira <= 30:
            raise ErroDeRegra('Os assentos por fileira precisam ficar entre 1 e 30.')
        repo.salvar_sala(Sala(0, f['nome'].strip(), fileiras, por_fileira, True))
        flash(f'Sala {f["nome"]} cadastrada com {fileiras * por_fileira} assentos.')
    except (ErroDeRegra, ValueError, KeyError) as e:
        flash(f'Não deu para cadastrar: {e}')
    except Exception:
        flash('Já existe uma sala com esse nome.')
    return redirect(url_for('salas'))


# ---------------------------------------------------------------- RF04
@app.route('/sessoes')
@exige_gerente
def sessoes():
    return render_template('sessoes.html', sessoes=repo.listar_sessoes(),
                           filmes=repo.listar_filmes(so_ativos=True),
                           salas=repo.listar_salas(so_ativas=True))


@app.route('/sessoes/nova', methods=['POST'])
@exige_gerente
def sessao_nova():
    f = request.form
    try:
        filme = repo.buscar_filme(int(f['filme_id']))
        sala = repo.buscar_sala(int(f['sala_id']))
        inicio = datetime.fromisoformat(f['inicio'])
        s = servico_sessao.programar(filme, sala, inicio, f['formato'],
                                     float(f['preco_inteira']))
        flash(f'Sessão programada: {filme.titulo} em {sala.nome}, '
              f'{s.inicio:%d/%m %H:%M} às {s.fim:%H:%M}.')
    except ErroDeRegra as e:
        flash(str(e))
    except (ValueError, KeyError, AttributeError):
        flash('Preencha filme, sala, data e hora, formato e preço.')
    return redirect(url_for('sessoes'))


# ---------------------------------------------------------------- RF06
@app.route('/sessao/<int:sessao_id>/comprar', methods=['GET', 'POST'])
def comprar(sessao_id):
    servico_compra.liberar_expirados()
    sessao = repo.buscar_sessao(sessao_id)
    if not sessao:
        flash('Sessão não encontrada.')
        return redirect(url_for('programacao'))

    if request.method == 'POST':
        ids = [int(i) for i in request.form.getlist('lugares')]
        try:
            compra = servico_compra.abrir(sessao, ids, request.form.get('cliente_nome', ''))
            return redirect(url_for('compra', compra_id=compra.id))
        except ErroDeRegra as e:
            flash(str(e))

    mapa = repo.mapa_da_sessao(sessao_id)
    agora = datetime.now()
    fileiras = {}
    for lugar in mapa:
        fileiras.setdefault(lugar.codigo[0], []).append((lugar, lugar.disponivel(agora)))
    return render_template('mapa.html', sessao=sessao, fileiras=fileiras)


# ---------------------------------------------------------------- RF07 e RF08
@app.route('/compra/<int:compra_id>', methods=['GET', 'POST'])
def compra(compra_id):
    c = servico_compra.buscar_compra(compra_id)
    if not c:
        flash('Compra não encontrada.')
        return redirect(url_for('programacao'))
    sessao = repo.buscar_sessao(c.sessao_id)

    if request.method == 'POST' and c.status == 'aberta':
        acao = request.form.get('acao')
        if acao == 'meias':
            servico_compra.definir_meias(c, {int(i) for i in request.form.getlist('meia')})
            flash(f'Total atualizado: R$ {c.total:.2f}')
        elif acao == 'pagar':
            try:
                pagamento = servico_compra.pagar(c)
                if pagamento.status == 'aprovado':
                    flash(f'Pagamento aprovado. Transação {pagamento.transacao_id}.')
                else:
                    flash('Pagamento recusado pelo gateway. Os assentos seguem reservados '
                          f'por até {MINUTOS_BLOQUEIO} minutos; dá para tentar de novo.')
            except ErroDeRegra as e:
                flash(str(e))
        return redirect(url_for('compra', compra_id=compra_id))

    c = servico_compra.buscar_compra(compra_id)
    itens = servico_compra.ingressos_da_compra(compra_id)
    expira = c.criada_em + timedelta(minutes=MINUTOS_BLOQUEIO)
    return render_template('compra.html', compra=c, sessao=sessao, itens=itens,
                           expira=expira, agora=datetime.now())


if __name__ == '__main__':
    repo.criar_tabelas()
    print('CineFlow em http://127.0.0.1:5000')
    app.run(debug=False, port=5000)
