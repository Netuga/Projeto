# APIs do site

- `auth.php`: sessão e autenticação.
- `units.php`: lista (`action=list`), estatísticas (`action=stats`) e recentes (`action=recent`).
- `gamemodes.php`: lista por modo (`action=list`) e resumo do modo mais recente (`action=overview`).
- `catalog.php`: catálogo de `item`, `accessory` ou `weapon` (`action=list&type=...`).

As páginas chamam estes endpoints para apresentar os registos gravados pelo painel admin. Os ficheiros `api_units.php`, `api_gamemodes.php` e `admin_extras.php` na raiz são apenas rotas de compatibilidade.
