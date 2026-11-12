# CineFlow – versão em Python

Sistema de gestão de cinema escrito em Python 3 com Flask e SQLite.
Trabalho da disciplina de Laboratório de Engenharia de Software (Unilasalle).

## Como rodar

**macOS ou Linux:** `./iniciar.sh`
**Windows:** dê dois cliques em `iniciar.bat`

O script cria o ambiente virtual, instala o Flask, cria o banco com dados de
demonstração e abre o sistema em http://127.0.0.1:5000.

Usuários de demonstração (senha `cineflow123`):

| Perfil | E-mail |
|---|---|
| Gerente do Cinema | gerente@cineflow.test |
| Atendente de Bilheteria | atendente@cineflow.test |

Para voltar os dados ao estado inicial: `./resetar-dados.sh`

## Testes

```
./.venv/bin/python -m unittest discover -s testes -v
```

São 26 testes cobrindo as regras de negócio: cálculo do término da sessão,
conflito de horário, geração da planta de assentos, bloqueio temporário de
assentos, meia-entrada e pagamento aprovado e recusado.

## Organização do código

| Arquivo | O que tem |
|---|---|
| `modelos.py` | As classes do domínio, uma por tabela do banco |
| `repositorio.py` | Todo o acesso ao SQLite. O resto do sistema não escreve SQL |
| `servicos.py` | As regras de negócio: programar sessão e conduzir a compra |
| `app.py` | As rotas do Flask: recebem o pedido e devolvem a tela |
| `templates/` | As telas em HTML |
| `dados_demo.py` | Recria o banco com filmes, salas e sessões de exemplo |
| `docs/diagramas/` | Diagramas UML em PlantUML, com os PNG gerados |
| `docs/telas/` | Capturas das telas do sistema |

A ordem das camadas é sempre a mesma: `app.py` chama um serviço ou o
repositório, o serviço chama o repositório, e só o repositório fala com o banco.
