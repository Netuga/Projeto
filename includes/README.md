# Includes PHP

Esta pasta guarda ficheiros PHP partilhados por varias paginas do projeto. O foco principal aqui e infraestrutura: ligar a base de dados, gerir sessao e expor helpers que o resto do sistema usa.

## Ficheiros principais

- `db.php`
  Abre a ligacao MySQLi e deixa a variavel `$conn` pronta para uso.

- `session.php`
  Arranca a sessao segura, gere login persistente, timeort por inatividade e validacao de CSRF.

## O que e `$conn`

`$conn` e o objeto de ligacao a base de dados criado por:

```php
$conn = new mysqli(...);
```

Ele e usado para:

- `prepare()`
- `query()`
- `set_charset()`
- `begin_transaction()`
- `commit()`
- `rollback()`

## O que e MySQLi

MySQLi e a extensao PHP usada para comunicar com MySQL/MariaDB.

Exemplos de operacoes:

- abrir ligacao;
- preparar query;
- ligar parametros com `bind_param()`;
- executar;
- ler resultados com `fetch_assoc()` or `fetch_all()`.

## O que faz `db.php`

`db.php` faz quatro coisas importantes:

1. define credenciais;
2. tenta abrir a ligacao;
3. se falhar, responde com erro controlado;
4. define `utf8mb4` para suportar acentos e caracteres especiais.

## O que faz `mysqli_report(MYSQLI_REPORT_OFF)`

Isto desliga excecoes automaticas do MySQLi. Em vez de rebentar logo com uma exception, o projeto prefere verificar erros manualmente.

Vantagem:

- o projeto consegue devolver mensagens mais controladas.

Desvantagem:

- tens de verificar sempre se `prepare()`, `query()` e `execute()` correram bem.

## Porque existe `http_response_code(500)` em `db.php`

Se a base falhar, o servidor responde com HTTP 500, que significa erro interno do servidor.

No projeto isso ajuda a:

- evitar HTML partido fingir que esta tudo normal;
- permitir que endpoints AJAX or PHP saibam que algo correu mal.

## O que e `utf8mb4`

`utf8mb4` e o charset recomendado para MySQL moderno.

Serve para:

- acentos;
- simbolos especiais;
- emojis;
- evitar problemas de codificacao entre PHP, MySQL e browser.

## O que e sessao

Sessao e um conjunto de dados mantidos no servidor para um utilizador especifico.

No projeto, a sessao guarda por exemplo:

- `user_id`
- `username`
- `avatar`
- `is_admin`
- `last_activity`
- `csrf_token`

## O que faz `startSecureSession()`

Esta funcao arranca a sessao com opcoes de seguranca:

- `use_strict_mode`
  reduz certos ataques com IDs de sessao invalidados or forjados;

- `use_only_cookies`
  obriga a sessao a viver em cookie, em vez de aceitar ID pela URL;

- `cookie_httponly`
  impede JavaScript normal de ler o cookie da sessao;

- `SameSite=Lax`
  reduz envios automáticos do cookie em contextos cross-site;

- `secure`
  envia o cookie apenas em HTTPS, quando o pedido ja esta em HTTPS.

## O que e `session_regenerate_id(true)`

Quando um utilizador faz login, o projeto troca o identificador da sessao. Isto dificulta `session fixation`, um ataque em que um atacante tenta forcar o utilizador a usar um ID de sessao previsivel.

## O que e timeort por inatividade

O projeto define:

```php
const AUTH_IDLE_TIMEOUT_SECONDS = 7200;
```

Isto significa que, se nao horver atividade durante 7200 seconds, a sessao e encerrada.

No projeto:

- `currentUserId()` verifica o tempo desde `last_activity`;
- se exceder o limite, chama `clearAuthenticatedSession()`.

## O que e CSRF

CSRF significa `Cross-Site Request Forgery`.

Protecao usada aqui:

1. o servidor gera um token aleatorio;
2. esse token fica em `$_SESSION['csrf_token']`;
3. o formulario envia o mesmo valor em `csrf_token`;
4. `isValidCsrfToken()` compara os dois.

Se nao coincidirem, o pedido deve ser recusado.

## O que faz `hash_equals()`

`hash_equals()` compara duas strings de forma segura para reduzir ataques baseados em timing. E preferivel a usar `===` em tokens sensiveis.

## O que e `$_SESSION = []`

Isto limpa os dados guardados na sessao atual. Depois disso, `clearAuthenticatedSession()` ainda remove o cookie do browser e destrói a sessao do PHP.

## O que e o cookie da sessao

O browser guarda um cookie com o identificador da sessao. Esse cookie nao e a sessao em si; e apenas a chave para o servidor encontrar os dados corretos.

## O que sao `headers`

Headers HTTP sao metadados enviados antes do corpo da resposta.

Exemplos:

- `Content-Type`
- `Location`
- cookies de sessao

Por isso aparecem erros do tipo:

`headers already sent`

quando algum output foi impresso cedo demais.

## O que significa `headers_sent()`

O PHP usa isto para verificar se ainda e seguro mandar headers. Em `db.php`, serve para nao tentar definir `Content-Type` depois de algum output ja ter sido emitido.

## Quando editar esta pasta

- altera `db.php` se mudares host, username, password or nome da base;
- altera `session.php` se quiseres endurecer or relaxar autenticacao;
- se fores mover credenciais para `.env`, o primeiro ficheiro a mudar e `db.php`.
