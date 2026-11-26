# Anime Rush

Projeto do site e do painel de administração. Os ficheiros da aplicação estão agrupados por função; os ficheiros pequenos na raiz mantêm os endereços antigos a funcionar.

## Pastas

| Pasta | Conteúdo |
|---|---|
| `pages/` | Páginas do site: `home`, `units`, `gamemodes`, `items`, `guides`, `profile`, `login` e páginas legais. |
| `api/` | APIs de autenticação, units, gamemodes e catálogo (`auth.php`, `units.php`, `gamemodes.php`, `catalog.php`). |
| `admin/` | Painel, módulos, serviços, estilos e setup da base de dados. |
| `assets/css/` | Estilos partilhados e estilos das páginas. |
| `assets/js/` | JavaScript partilhado e scripts das páginas. |
| `assets/img/` | Logótipos, fundos e imagens. |
| `assets/skins/` | Imagens de skins. |
| `config/` | Configuração da aplicação e exemplo de configuração local. |
| `includes/` | Ligação à base de dados e funções de sessão/segurança. |
| `google/`, `discord/`, `roblox/` | Fluxos OAuth de cada fornecedor. |

## Entradas principais

- Página inicial: `pages/home.html` (também acessível por `index.html`).
- Adicionar/editar units: `admin/index.php?module=units`.
- Painel admin: `admin/index.php`.
- Configuração principal: `config/app.php`, carregada pelo atalho `config.php`.
- APIs: `api/auth.php`, `api/units.php`, `api/gamemodes.php` e `api/catalog.php`.

Os atalhos HTML e PHP na raiz encaminham para os novos ficheiros e preservam os endereços antigos usados por callbacks, páginas guardadas e integrações. A configuração privada continua em `config.local.php` na raiz e não deve ser publicada.
