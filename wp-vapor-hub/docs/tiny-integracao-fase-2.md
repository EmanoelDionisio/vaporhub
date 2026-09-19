# Tiny ERP — Integração Fase 2

Plano de implementação para a próxima rodada de melhorias de segurança, robustez e UX da integração Tiny no plugin **Minha Loja**.

**Contexto:** A Fase 1 (v3.7.5) endereçou o incidente de categorias duplicadas, validação de vínculos, default “Ignorar”, confirmação ao criar, bloqueio de push sem mapeamento, fila com lock otimista e recovery de jobs presos.

**Versão alvo sugerida:** 3.8.0

---

## Prioridade 1 — Crítico (ERP / perda de dados)

### 1.1 Sync de imagens v3 sem deletar produto

**Problema:** Quando o Tiny exige recriar o cadastro para importar imagens, o driver pode **deletar** o produto e recriar. Se a recriação falhar, o produto some do ERP.

**Arquivos:** `includes/integrations/tiny/class-vh-tiny-driver-v3.php`, `includes/services/class-vh-tiny-sync-service.php`

**Tarefas:**
- [ ] Mapear fluxo atual (`vh_tiny_imagem_recriar`) e pontos de DELETE na API v3
- [ ] Substituir delete+recreate por: upload incremental, endpoint alternativo, ou flag manual “Recriar cadastro no Tiny” (opt-in explícito)
- [ ] Guardar `tiny_id` anterior em meta temporária antes de qualquer operação destrutiva
- [ ] Teste: produto com imagem WebP, produto variável com galeria, falha simulada na recriação

**Critério de aceite:** Nenhum DELETE automático de produto sem confirmação explícita do gestor.

---

### 1.2 Reconciliação paginada (catálogo completo)

**Problema:** `reconciliar()` enfileira push só dos **primeiros 100** produtos WC. Pull v2 limita a ~3 páginas (~150 itens). Loja com ~1600 produtos fica parcialmente fora da reconciliação.

**Arquivos:** `includes/services/class-vh-tiny-sync-service.php`, `includes/class-vh-tiny-queue.php`

**Tarefas:**
- [ ] Push: paginar todos os IDs de produtos publicados ou enfileirar só os com hash divergente (`produto_pendente_envio`)
- [ ] Pull v2: iterar até `total` retornado pela API
- [ ] Job `reconciliar` idempotente — não duplicar fila a cada cron se já há backlog
- [ ] UI: painel Tiny mostra “X de Y produtos reconciliados” / pending restante

**Critério de aceite:** Reconciliação manual + cron cobrem 100% do catálogo publicado, em lotes, sem timeout PHP.

---

### 1.3 Webhook — token fora da URL

**Problema:** Token na URL (`/public/tiny/webhook/{token}`) pode vazar em logs, Referer e analytics. Nome do campo `webhook_token_hash` guarda valor em plaintext.

**Arquivos:** `includes/rest/class-vh-rest-tiny.php`, `includes/integrations/tiny/class-vh-tiny.php`

**Tarefas:**
- [ ] Aceitar token via header `X-PA-Tiny-Token` (manter URL legado por compatibilidade temporária)
- [ ] Armazenar `hash('sha256', token)` — migrar instalações existentes na ativação
- [ ] Botão “Rotacionar token webhook” no painel + invalidar URL antiga
- [ ] Webhook: **apenas enfileirar** evento; não chamar `processar_lote()` inline (evitar timeout)

**Critério de aceite:** Novos ambientes usam header; rotação não quebra Tiny até atualizar URL no painel do ERP.

---

## Prioridade 2 — Alto (UX / consistência)

### 2.1 Mapeamento de categorias — árvore visual

**Problema:** Lista plana com 70+ categorias Tiny dificulta vínculo correto (`POD` vs `PODs`, homônimos).

**Arquivos:** `admin/js/tiny.js`, `admin/views/integracoes/tiny-mapeamento.php`, `admin/css/admin.css`

**Tarefas:**
- [ ] Indentar categorias da loja por nível hierárquico
- [ ] Dropdown Tiny agrupado por raiz (optgroup ou árvore colapsável)
- [ ] Destaque visual quando caminho loja ≈ caminho Tiny (sugestão automática de vínculo)
- [ ] Botão **Desvincular** por linha (só meta local, sem DELETE no Tiny)

---

### 2.2 `buscar_categoria` — match só por caminho completo

**Problema:** Auto-match por nome folha gera falso positivo e duplicatas no fluxo “Criar”.

**Arquivos:** `includes/integrations/tiny/class-vh-tiny-driver-v3.php`, `includes/services/class-vh-tiny-category-sync.php`

**Tarefas:**
- [ ] Remover comparação por nome isolado; exigir caminho hierárquico normalizado
- [ ] Cache transiente (5–15 min) de `listar_categorias` por request/batch
- [ ] Log/retorno quando auto-match encontrar candidato ambíguo

---

### 2.3 Wizard de reconexão / troca de conta Tiny

**Problema:** `desconectar()` não limpa `_vh_tiny_id`, `_vh_tiny_cat_id`. Reconectar outra conta reutiliza vínculos inválidos.

**Arquivos:** `includes/integrations/tiny/class-vh-tiny.php`, `admin/views/integracoes/tiny.php`, `admin/js/tiny.js`

**Tarefas:**
- [ ] Modal ao desconectar: “Manter vínculos” vs “Limpar vínculos locais”
- [ ] Listar resumo antes de limpar (N produtos, M categorias vinculadas)
- [ ] Nunca apagar dados no Tiny — só metadados locais

---

### 2.4 Exclusão de produto WC → aviso no Tiny

**Problema:** Excluir produto na loja só remove meta local; cadastro permanece ativo no ERP.

**Arquivos:** `includes/class-vh-tiny-module.php`, `includes/services/class-vh-tiny-sync-service.php`

**Tarefas:**
- [ ] Job opcional `produto_inativar` (situação `I` no Tiny) — best-effort
- [ ] Aviso na UI ao mover produto para lixeira se tinha `_vh_tiny_id`
- [ ] Documentar no manual do cliente: exclusão na loja ≠ exclusão no ERP

---

## Prioridade 3 — Médio (qualidade operacional)

### 3.1 Pull de produtos variáveis

**Problema:** Import pull de produto variável é recusado; webhook/reconciliação não traz pais variáveis novos do Tiny.

**Tarefas:**
- [ ] Definir escopo com cliente: importar pai + variações por SKU ou só atualizar existentes
- [ ] Implementar fluxo mínimo ou documentar limitação explícita na UI

---

### 3.2 SKU auto-gerado antes do push

**Problema:** SKU gerado só no push; UI mostra “Sem SKU” até enviar.

**Tarefas:**
- [ ] Gerar SKU na criação da variação (opcional, configurável) ou banner “SKU será gerado no envio ao Tiny”
- [ ] Evitar mudança silenciosa de SKU após primeiro push

---

### 3.3 Contadores e dashboard honestos

**Tarefas:**
- [ ] `contar_alteracoes_locais_pendentes`: COUNT SQL sem limite 2000
- [ ] `estado_produto` para pai variável: considerar OK se variações têm SKU
- [ ] Coluna Tiny na lista de produtos visível em modo “somente recebimento” (read-only)

---

### 3.4 Fila — status `skipped` e limpeza de `failed`

**Tarefas:**
- [ ] Status `skipped` quando direção desligada (em vez de requeue infinito em edge cases)
- [ ] UI para reprocessar jobs `failed` e limpar antigos
- [ ] Métricas: tempo médio na fila, jobs presos

---

## Prioridade 4 — Baixo / hardening

- [ ] `.htaccess` ou endpoint autenticado para JPEGs em `uploads/vh-tiny-sync/`
- [ ] Rate limit webhook por token + IP (Forwarded-For com cautela)
- [ ] Bloquear troca v2↔v3 com conexão ativa sem desconectar
- [ ] Validar `definir_modo` e payload máximo em todos os endpoints REST Tiny

---

## Ordem sugerida para amanhã

| Ordem | Item | Estimativa |
|------:|------|------------|
| 1 | 2.1 Árvore visual + Desvincular | 3–4 h |
| 2 | 2.2 buscar_categoria caminho completo | 1–2 h |
| 3 | 1.2 Reconciliação paginada | 3–4 h |
| 4 | 1.1 Imagens sem DELETE | 4–6 h |
| 5 | 1.3 Webhook header + hash | 2–3 h |
| 6 | 2.3 Wizard reconexão | 2 h |

---

## Checklist de deploy (cada entrega)

- [ ] `php -l` nos arquivos PHP alterados
- [ ] Bump `VH_LOJA_VERSION` em `vapor-hub-loja.php`
- [ ] `npm run wp:deploy`
- [ ] Hard refresh (Ctrl+Shift+R) no painel Minha Loja
- [ ] Validação remota via WP-CLI/MCP quando aplicável
- [ ] Teste manual: mapeamento categorias, salvar produto com/sem envio, fila Tiny

---

## Referências no código (Fase 1 entregue)

| Área | Arquivo principal |
|------|-------------------|
| Mapeamento categorias | `includes/services/class-vh-tiny-mapping-service.php` |
| Sync produtos | `includes/services/class-vh-tiny-sync-service.php` |
| Fila | `includes/class-vh-tiny-queue.php` |
| REST Tiny | `includes/rest/class-vh-rest-tiny.php` |
| UI mapeamento | `admin/js/tiny.js`, `admin/js/custom-select.js` |
| Meta ignorar categoria | `_vh_tiny_cat_ignorar` em `class-vh-tiny-map.php` |

---

*Documento gerado após auditoria de segurança da integração Tiny (jun/2025). Atualizar conforme itens forem concluídos.*
