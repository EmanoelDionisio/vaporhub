# Checklist de Testes — App Minha Loja (v2.0.0)

Roteiro de validação manual após o deploy. Execute com **dois usuários**: um com a role `gestor_loja` (sem `manage_options`) e um `administrator`.

## Pré-requisitos

- [ ] Plugin **Vapor Hub - Minha Loja** ativo na versão 2.0.0
- [ ] WooCommerce ativo
- [ ] Após ativar/atualizar, os permalinks foram regravados (a ativação e o upgrade chamam `flush_rewrite_rules`). Se `/minha-loja/` der 404, acesse Configurações › Links Permanentes e clique em Salvar.

## Roteamento e portal público

- [ ] Visitante não logado em `/minha-loja/` é levado ao login e, após autenticar, cai no painel
- [ ] `/minha-loja/pedidos` abre a seção Pedidos
- [ ] `/minha-loja/produtos/{id}` abre o produto correspondente em edição
- [ ] Usuário logado sem a capacidade `gerenciar_vh_loja` em `/minha-loja/` é redirecionado para a home
- [ ] Slugs legados (`admin.php?page=vh-loja-aparencia`, etc.) redirecionam para a nova rota

## Isolamento do Gestor da Loja (`gestor_loja`)

- [ ] Após o login, o gestor cai em `admin.php?page=vh-loja`
- [ ] A barra lateral nativa do WordPress fica oculta na tela do app (modo isolado)
- [ ] Acessar `admin.php?page=wc-orders` redireciona para o app
- [ ] Acessar `edit.php?post_type=product` redireciona para o app
- [ ] Acessar `post-new.php?post_type=product` redireciona para o app
- [ ] Acessar `users.php`, `plugins.php`, `themes.php`, `options-general.php`, `tools.php` redireciona para o app
- [ ] `/wp-admin/` (index) redireciona para o app
- [ ] O modal de mídia (selecionar imagem) funciona normalmente
- [ ] Salvar Aparência/Comunidade/Revenda funciona (capacidade do Settings API mapeada para `gerenciar_vh_loja`)

## Administrador (`administrator`)

- [ ] Vê o menu lateral nativo do WordPress junto do app (não fica isolado)
- [ ] Continua com acesso total a WooCommerce, produtos, usuários, plugins, etc.
- [ ] Consegue abrir o app pelo menu "Minha Loja" e pela barra superior

## Pedidos

- [ ] Lista carrega com paginação e filtros (busca, status, datas)
- [ ] Detalhe mostra itens, totais e notas
- [ ] Alterar status salva e reflete na lista
- [ ] Código de rastreio é salvo
- [ ] Edição de endereços de cobrança/entrega é salva
- [ ] Adicionar nota privada funciona; nota ao cliente dispara e-mail
- [ ] Nenhum link aponta para a tela nativa de edição de pedido

## Produtos e Categorias

- [ ] Criar produto simples (nome, preço, imagem, categorias, destaques) publica corretamente
- [ ] Editar produto salva as alterações
- [ ] Galeria e imagem principal salvam os anexos selecionados
- [ ] Excluir produto move para a lixeira
- [ ] Criar/editar categoria com imagem funciona
- [ ] Excluir categoria com produtos é bloqueado com mensagem clara

## Clientes e Cupons

- [ ] Lista de clientes busca por nome/e-mail
- [ ] Detalhe do cliente mostra histórico de pedidos com links internos
- [ ] Criar cupom (percentual e valor fixo) funciona
- [ ] Código duplicado é rejeitado
- [ ] Editar e excluir cupom funcionam

## Relatórios

- [ ] KPIs de receita (hoje/7/30/mês) e ticket médio aparecem
- [ ] Pedidos por status e Top 5 produtos são exibidos

## Segurança (REST `vh-loja/v1`)

- [ ] Chamar qualquer endpoint **sem autenticação** retorna 401/403
- [ ] Chamar endpoint autenticado **sem** `gerenciar_vh_loja` retorna 403
- [ ] Requisições de escrita exigem o nonce `wp_rest` (enviado pelo app)
- [ ] IDs inexistentes retornam 404 sem vazar dados
