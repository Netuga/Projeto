# JavaScript

Esta pasta guarda o JavaScript partilhado do site. Neste momento o ficheiro principal aqui e `shared.js`.

## O que e "JavaScript partilhado"

Significa codigo que pode ser usado por varias paginas da wiki, em vez de existir so para uma pagina especifica.

Exemplo:

- cursor customizado;
- helpers globais;
- funcoes visuais comuns.

## Ficheiros desta pasta

- `shared.js`
  Script comum do site.

- `README.md`
  Esta documentacao.

## Importante sobre o painel admin

O JavaScript antigo do painel admin foi guardado em:

- `admin/legacy/assets/js/panel.js` (não é carregado pelo painel atual).

Isto foi feito para separar:

- comportamento global do site;
- comportamento especifico da area administrativa.

## Conceitos de JavaScript usados no projeto

### DOM

DOM e a representacao da pagina em memoria. E isso que o JavaScript manipula quando usa:

- `document.getElementById(...)`
- `document.querySelector(...)`
- `element.innerHTML`
- `element.classList`

### Event listener

Um event listener e uma funcao que reage a eventos do browser, por exemplo:

- clique;
- mudanca de input;
- tecla premida.

Exemplo:

```js
button.addEventListener('click', () => { ... });
```

### `dataset`

Quando o HTML tem algo como:

```html
<button data-edit-unit-id="15">
```

o JavaScript pode ler com:

```js
button.dataset.editUnitId
```

Isto e muito usado no painel para editar or apagar registos sem meter IDs hardcoded no JS.

### `Set`

`Set` e uma colecao sem duplicados.

No painel admin, serve para guardar elementos selecionados da unit, sem repetir:

- `Dark`
- `Water`
- `Fire`

### `Map`

`Map` e uma estrutura chave -> valor.

No painel admin, e usado para caches por ID:

- `unit id -> unit object`
- `mode id -> mode object`
- `extra id -> extra object`

Isto evita percorrer arrays grandes sempre que o utilizador carrega em "Edit".

### `JSON.stringify()` e `JSON.parse()`

Estas funcoes convertem entre objeto JS e texto JSON.

- `JSON.stringify(obj)`
  transforma objeto em texto

- `JSON.parse(texto)`
  transforma texto em objeto

No painel admin, isto e util para:

- montar `unit_elements_payload`;
- ler `window.__ADMIN_DATA__`;
- reconstruir estado que veio do PHP.

### Serializacao

Serializar e transformar dados visuais em texto or JSON para enviar.

Exemplo do painel:

- o utilizador mexe em varias linhas de skins;
- o JS junta tudo numa string unica;
- essa string vai num campo escondido;
- o PHP recebe e interpreta.

### Hidratacao

Hidratacao aqui nao significa SPA complexa. Significa apenas que o PHP entrega dados ao HTML e o JS reconstrói o estado visual inicial.

Exemplo:

- o PHP imprime `window.__ADMIN_DATA__`;
- o JS le essa variavel;
- o JS preenche preview, caches e formulários.

### Event delegation

Em vez de adicionar listeners em cada botao individual, o JS pode orvir no `document` e decidir o que fazer consoante o alvo clicado.

Vantagem:

- funciona bem com elementos criados dinamicamente;
- reduz repeticao de listeners.

## Porque o CRUD principal ja nao esta no JavaScript

Antes era facil cair num painel onde o JS decidia quase tudo. Agora a responsabilidade principal foi passada para o PHP.

O JavaScript ficor limitado a:

- tabs;
- preview;
- modal de confirmacao;
- reconstruir formularios em modo edicao;
- serializar campos dinamicos antes do submit.

Isto torna o painel:

- mais previsivel;
- mais facil de depurar;
- mais seguro do que fazer o CRUD principal no browser.
