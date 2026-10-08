# Contexto atual — Vapor Hub

Loja WooCommerce neste repositório. Não mexer em Piscou Afundou, CIA do Vapor nem em produção até pedirem. Não fazer commit nem push sem pedido.

Último envio no GitHub: menus (`a267ce8`) e paginação (`375ceac`) em `main`. O que veio depois está só na máquina, sem commit. Versões locais: plugin `3.16.32`, tema `1.0.97`.

## Home

Ordem fixa: banner, benefícios, destaques, categorias, faixa de ofertas, mais vendidos, faixa de destaque. Textos e interruptores em Aparência → Página inicial (`vh_home`). Cores e marcas em Aparência → Cores.

A faixa de ofertas usa a cor principal do painel. Link vazio abre a loja com `vh_promocao=1`. A faixa de destaque é um cartão com superfície, texto e borda do tema. Link vazio abre a loja.

No celular, destaques e mais vendidos ficam em duas colunas, com o nome em duas linhas e o botão na largura do card. A partir de 768 px voltam à grade de quatro. A loja em si não muda.

Marcas separadas: modo claro (`logo_url`), modo escuro (`logo_escuro_url`), painel (`logo_painel_url`) e favicon. Escura ou do painel vazia reutiliza a clara. O recorte das três marcas é o mesmo perfil: o quadro acompanha a arte e a imagem salva cabe em até 400×160, proporcional, sem esticar. Favicon e as outras imagens continuam no retângulo fixo. O modo escuro (fundo, superfície, texto, texto suave, borda) sai do painel. A cor principal é a mesma nos dois modos.

## O que saiu da vitrine

Revenda e comunidade não aparecem mais na home, no rodapé nem no menu principal. Os popups não são mais injetados em toda página. Os módulos Revenda e Comunidade do painel, o REST e os dados continuam. A página `/comunidade/` existe e não está no menu.

## Não regredir

Paginação no caminho da categoria, em `/page/N/`, sem `orderby=menu_order` por padrão. Ícones do painel pela biblioteca visual. Abas da Aparência. Cores da vitrine vindas do painel.

## De fora até pedirem

Apagar Revenda e Comunidade do painel. Corrigir o SQL do filtro de promoção. Recolocar “Loja” no menu principal.
