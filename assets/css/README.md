# CSS

Esta pasta guarda os estilos partilhados do site. O ficheiro principal aqui e `shared.css`.

## Ficheiros desta pasta

- `shared.css`
  Base visual comum do projeto.

- `README.md`
  Esta documentacao.

## O que e CSS partilhado

E o CSS que deve servir varias paginas:

- tipografia base;
- cores base;
- resets;
- classes estruturais;
- componentes reutilizados.

O CSS específico do painel admin vive noutra pasta:

- `admin/assets/css/admin.css`
- O estilo antigo combinado foi guardado em `admin/legacy/assets/css/panel.css`.

## Conceitos de CSS usados no projeto

### Variaveis CSS

Tambem chamadas custom properties:

```css
--text: #eaf2ff;
--muted: #94a0b8;
```

Depois podem ser usadas assim:

```css
color: var(--text);
```

Isto ajuda a manter:

- consistencia visual;
- cores centralizadas;
- manutencao mais simples.

### Flexbox

Usado quando elementos devem alinhar em linha or coluna com controlo de espacamento.

Exemplos tipicos:

- barras de botoes;
- menus;
- grupos de badges.

### CSS Grid

Usado quando a pagina precisa de grelhas mais estruturadas.

No painel admin isto aparece muito nas:

- `form-grid`
- areas de preview
- listagens

### Classe ativa

Muitas partes do site mudam visualmente com uma classe como:

```css
.tab-btn.active { ... }
```

O JavaScript nao injeta estilo diretamente; em muitos casos limita-se a ligar/desligar classes.

### Atributo `hidden`

O HTML pode esconder um elemento com:

```html
<div hidden>
```

Depois o CSS e/or JS decide quando mostrar.

Isto aparece muito em:

- modais;
- previews por tab;
- banners de edicao;
- blocks so para admin.

### Gradientes

O projeto usa bastantes `linear-gradient(...)` para:

- fundos de cards;
- banners;
- previews;
- fallback visual quando nao ha imagem.

### Responsividade

Responsividade significa que o layout se adapta a desktop e mobile.

No projeto isso costuma ser tratado com:

- `max-width`;
- `grid-template-columns`;
- media queries;
- `clamp(...)` para tamanhos flexiveis.

## Como o CSS do painel se relaciona com esta pasta

Fluxo normal:

1. a pagina carrega `assets/css/shared.css`;
2. depois carrega `admin/assets/css/admin.css`.
3. o CSS do painel especializa o visual sem destruir a base comum do site.

Isto foi feito para o admin continuar com o mesmo "look" geral do site, em vez de virar uma pagina totalmente diferente.
