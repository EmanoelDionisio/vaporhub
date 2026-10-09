# Contexto atual — Vapor Hub

Loja WooCommerce neste repositório. Não mexer em Piscou Afundou, CIA do Vapor nem em produção até pedirem. Não fazer commit nem push sem pedido.

Último envio no GitHub: o Turnstile do painel cobre os formulários públicos da loja (`913a709`) em `main`. Versões: plugin `3.16.34`, tema `1.0.108`.

## Segurança

Cliente logado sem `manage_options` e sem `gerenciar_vh_loja` que abre `/minha-loja/`, `/wp-admin/` ou `wp-login.php` volta para a própria conta, sem aviso. A segunda tentativa, em 15 minutos, encerra a sessão do mesmo jeito. Deslogado, essas duas URLs do WordPress vão para a home. A porta só abre com `define( 'VH_LOGIN_WORDPRESS', true );` no `wp-config` do volume. Senha perdida e erro de senha ficam em `/minha-conta/`. `admin-ajax.php` e `admin-post.php` continuam livres. O Turnstile, quando ligado no painel, entra uma vez em cada formulário público: entrar, cadastro, senha, contato, avaliação e finalizar compra. Desligado, esses envios seguem como antes. Busca e carrinho não passam por ele.

## Home

Ordem fixa: banner, benefícios, destaques, categorias, faixa de ofertas, mais vendidos, faixa de destaque. Textos e interruptores em Aparência → Página inicial (`vh_home`). Cores e marcas em Aparência → Cores.

A faixa de ofertas usa a cor principal do painel. Link vazio abre a loja com `vh_promocao=1`. A faixa de destaque é um cartão com superfície, texto e borda do tema. Link vazio abre a loja.

No celular, destaques e mais vendidos ficam em duas colunas, com o nome em duas linhas e o botão na largura do card. A partir de 768 px voltam à grade de quatro. A loja em si não muda.

A avaliação do produto usa as cores do painel: estrelas na cor principal, campo com fundo e borda do tema, e o envio no botão principal. No celular o botão ocupa a largura.

Na página do produto, no celular, a galeria, o nome, a compra e as abas ficam dentro da tela. As fotos extras rolam dentro da galeria.

A conta do cliente usa o mesmo cartão e o mesmo botão da loja. Esqueci a senha usa o formulário da conta, nesse cartão, com o botão principal da loja, e depois do e-mail volta para a tela de entrar. No celular o menu fica em lista acima do conteúdo; a partir do tablet, ao lado. Sem pedidos, o aviso e o botão ficam dentro do cartão. Com pedidos, a lista empilha no celular.

A descrição da categoria é o campo nativo do WordPress, editado em Minha Loja → Categorias. Na primeira página da categoria, o texto aparece num cartão. Se passar de quatro linhas, surge o botão Leia mais. O texto inteiro continua no HTML.

No celular, a grade de produtos da categoria e da loja fica em duas colunas dentro da tela. A faixa de departamentos rola para o lado em vez de alargar a página. O mesmo limite vale para os outros cards de produto. Se dois itens da faixa apontam para a mesma categoria, só o item com o nome da categoria fica marcado.

O nome do produto, na lista e na página do produto, sai de Aparência → Cores, no campo “Tamanho do nome do produto”. As opções são Compacto, Equilibrado e Em destaque. Equilibrado é o padrão e fica menor do que o tamanho anterior.

No produto variável, o clique em adicionar ao carrinho sem as opções mostra um aviso com o que falta e marca essa escolha. Com tudo escolhido, a compra segue. Se a combinação não existe, o aviso pede outras opções.

Marcas separadas: modo claro (`logo_url`), modo escuro (`logo_escuro_url`), painel (`logo_painel_url`) e favicon. Escura ou do painel vazia reutiliza a clara. No login e na lateral do painel, a marca do painel é pintada com a cor principal (`--vh-primario`), sem filtro branco fixo. O recorte das três marcas é o mesmo perfil: o quadro acompanha a arte e a imagem salva cabe em até 400×160, proporcional, sem esticar. Favicon e as outras imagens continuam no retângulo fixo. O modo escuro (fundo, superfície, texto, texto suave, borda) sai do painel. A cor principal é a mesma nos dois modos.

## O que saiu da vitrine

Revenda e comunidade não aparecem mais na home, no rodapé nem no menu principal. Os popups não são mais injetados em toda página. Os módulos Revenda e Comunidade do painel, o REST e os dados continuam. A página `/comunidade/` existe e não está no menu.

## Não regredir

Paginação no caminho da categoria, em `/page/N/`, sem `orderby=menu_order` por padrão. Ícones do painel pela biblioteca visual. Abas da Aparência. Cores da vitrine vindas do painel. A cerca do cliente não vale para administrador nem gestor.

## De fora até pedirem

Apagar Revenda e Comunidade do painel. Corrigir o SQL do filtro de promoção. Recolocar “Loja” no menu principal.
