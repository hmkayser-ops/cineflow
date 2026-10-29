# CineFlow

Sistema web de gestão de cinema: cadastro de filmes e salas, programação de sessões, venda de ingressos com marcação de assentos e validação na entrada.

Projeto da disciplina **Laboratório de Engenharia de Software (Unilasalle)**.
Grupo: Henrique Marques Kayser (Product Owner / Dev), Renan Pederiva Ferreira (Scrum Master / Dev), Vinicius Martins (Dev / Documentação UML).

## Status por sprint

| Sprint | Data | Requisitos | Situação |
|---|---|---|---|
| 1 | 29/10 | RF01 Autenticação com perfis · RF02 Cadastrar filme · RF03 Cadastrar sala com planta de assentos · RF04 Programar sessão | Implementado |
| 2 | 12/11 | RF05 Consultar programação · RF06 Comprar ingresso · RF07 Meia-entrada · RF08 Pagamento (simulado) | Planejado |
| 3 | 03/12 | RF09 Cancelar compra · RF10 Validar ingresso · RF11 Relatório de ocupação · RF12 Política promocional | Planejado |

## Tecnologias

- PHP 8.2+ com Laravel 12
- Banco SQLite em desenvolvimento (compatível com MySQL via `.env`)
- Interface em Blade + CSS próprio (sem etapa de build de front-end)
- Testes automatizados com PHPUnit

## Como rodar

O projeto já vem com a pasta `vendor` (dependências) incluída, então **não é preciso Composer nem internet**. Só é preciso ter o PHP 8.2 ou superior.

### 1. Instalar o PHP (uma vez)

O jeito mais simples é o **Laravel Herd** (gratuito, Windows e macOS): https://herd.laravel.com
Instale, abra uma vez e pronto: o comando `php` passa a funcionar no terminal.

Para conferir, abra o terminal (Prompt de Comando no Windows) e digite `php -v`. Deve aparecer a versão 8.2 ou maior.

### 2. Iniciar o sistema

- **Windows:** dê dois cliques em `iniciar.bat`
- **macOS / Linux:** no terminal, dentro da pasta do projeto, rode `sh iniciar.sh`

Na primeira execução o script cria o banco de dados com dados de demonstração. Depois abre o navegador em http://127.0.0.1:8000.
Para parar o sistema, feche a janela do terminal (ou `Ctrl + C`).

### 3. Entrar

| Perfil | E-mail | Senha |
|---|---|---|
| Gerente do Cinema | gerente@cineflow.test | cineflow123 |
| Atendente de Bilheteria | atendente@cineflow.test | cineflow123 |
| Operador de Portaria | portaria@cineflow.test | cineflow123 |

### Recriar os dados de demonstração

As sessões de demonstração são geradas a partir da data atual. Antes de apresentar, rode `resetar-dados.bat` (Windows) ou `sh resetar-dados.sh` para começar do zero com sessões de hoje, amanhã e depois.

## Rodar os testes

```
php vendor/bin/phpunit --testdox
```

38 testes cobrem autenticação e perfis, cadastro de filmes e salas, e todos os fluxos alternativos de "Programar sessão" (FA1 a FA4).

## Regras de negócio implementadas (Sprint 1)

**Programar sessão**
- Término = início + 15 min de trailers + duração do filme + 20 min de limpeza (configurável no `.env`).
- FA1: conflito de horário na mesma sala é bloqueado. O sistema mostra a sessão conflitante e sugere o primeiro horário livre.
- FA2: o filme precisa ter licença de exibição vigente na data.
- FA3: sala em manutenção não aceita sessão até a data prevista de liberação.
- FA4: campos obrigatórios e data no passado são validados, preservando o que foi digitado.
- Sessão em 3D só pode ser programada em sala equipada para 3D.
- O preço vem da tabela vigente do formato (meia = 50%) e pode ser ajustado.
- Programação em lote: replica a sessão por N dias. Os dias com impedimento são informados sem cancelar os demais.
- Ao programar, o mapa de assentos da sessão é gerado a partir da planta da sala.
- A gravação usa transação com bloqueio da sala, o que impede que duas programações simultâneas ocupem o mesmo horário.

**Segurança e auditoria**
- Senhas armazenadas com hash (bcrypt).
- Bloqueio de 1 minuto após 5 tentativas de login erradas.
- Cada perfil só acessa o que lhe cabe (middleware `perfil`).
- Login, logout e toda criação, alteração ou cancelamento de filme, sala, sessão e usuário ficam registrados na tela de Auditoria.

## Estrutura principal

```
app/
  Http/Controllers/        Auth, Filme, Sala, Sessao, Usuario, Auditoria
  Http/Middleware/         VerificarPerfil (controle de acesso por perfil)
  Models/                  User, Filme, Sala, Assento, Sessao, SessaoAssento, TabelaPreco, Auditoria
  Services/                ProgramacaoSessaoService (regras do caso de uso "Programar sessão")
config/cineflow.php        parâmetros de negócio
database/migrations/       estrutura do banco
database/seeders/          dados de demonstração
resources/views/           telas (Blade)
tests/Feature/             testes automatizados
```
