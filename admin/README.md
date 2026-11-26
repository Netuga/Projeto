# Painel de administração

O painel está dividido por módulos em `modules/`. Cada pasta contém a configuração dos campos e colunas do respetivo domínio; o layout, a validação de sessão, CSRF e as operações SQL comuns ficam partilhados.

## Estrutura

```text
admin/
├── index.php                  # entrada e navegação do painel
├── includes/
│   ├── bootstrap.php          # sessão, ligação e autorização de admin
│   └── module_view.php        # formulário e tabela comuns
├── services/
│   └── records.php            # leitura e CRUD parametrizado
├── modules/
│   ├── units/config.php
│   ├── gamemodes/config.php
│   ├── items/config.php
│   ├── accessories/config.php
│   ├── weapons/config.php
│   ├── users/config.php
│   ├── user_stats/config.php
│   └── login_attempts/config.php
└── assets/css/admin.css
```

`users`, `user_stats` e `login_attempts` são módulos de consulta. Os módulos de conteúdo suportam criação, edição e remoção. Para adicionar uma secção, cria uma pasta em `modules/`, define a tabela e os campos em `config.php` e regista a pasta na lista de `adminModules()` em `services/records.php`.

O acesso exige uma sessão válida e `users.is_admin = 1`. Os formulários e remoções usam token CSRF. As consultas de escrita usam prepared statements e só aceitam tabelas/campos definidos nos ficheiros de configuração dos módulos.

O endereço principal é `admin/index.php`. `admin.html` e `pages/panel.html` continuam como atalhos de compatibilidade. Os ficheiros antigos do painel combinado foram movidos para `legacy/` e não são carregados pela página nova.
