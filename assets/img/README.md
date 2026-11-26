# Imagens Globais

Esta pasta guarda imagens usadas diretamente pelo site.

## O que normalmente fica aqui

- logos;
- banners;
- fundos;
- imagens decorativas;
- imagens estaticas que nao pertencem a uma unit especifica ou a uma skin especifica.

## Caminhos de imagem no projeto

O projeto costuma guardar caminhos relativos na base de dados e no HTML, por exemplo:

```text
assets/img/banner.png
assets/img/logo.png
```

No painel admin, quando o PHP precisa de mostrar essas imagens a partir de `admin/index.php`, usa helpers para resolver o caminho correto a partir da localizacao do ficheiro atual.

## Diferenca entre caminho relativo e absoluto

- relativo
  Depende da pasta onde a pagina atual esta.
  Exemplo: `../../assets/img/banner.png`

- absoluto no site
  Comeca na raiz do dominio.
  Exemplo: `/assets/img/banner.png`

No projeto ha suporte para:

- caminhos relativos guardados na base;
- URLs completas `https://...`;
- imagens `data:...` quando necessario.

## Campos de imagem que aparecem no painel

Para units:

- `img`
  imagem fallback generica;

- `img_card`
  imagem usada no card/lista;

- `img_ingame`
  imagem mais orientada ao aspeto in-game;

- `img_minimal`
  imagem compacta para previews ou listas mais limpas.

Para extras:

- `img`
  imagem principal do item/soul/trait.

Para modes:

- `bg_image`
  imagem de fundo do cartao/preview do modo.

## O que e fallback de imagem

Fallback significa "plano B".

Exemplo no painel:

- se existir `img_minimal`, usa essa;
- se nao existir, tenta `img_card`;
- se nao existir, tenta `img`;
- se nao existir, tenta `img_ingame`;
- se nao houver nenhuma, mostra emoji ou texto curto.

## O que significa `loading="lazy"`

Algumas imagens sao renderizadas com:

```html
loading="lazy"
```

Isto diz ao browser para adiar o carregamento ate a imagem estar mais perto de aparecer no ecrã. Ajuda desempenho.

## O que e `alt`

`alt` e o texto alternativo da imagem. Serve para:

- acessibilidade;
- leitores de ecra;
- contexto quando a imagem falha.

## Quando usar `assets/img` e quando usar `assets/skins`

- `assets/img`
  para imagens globais do site;

- `assets/skins`
  para variantes visuais de personagens e skins ligadas a units.
