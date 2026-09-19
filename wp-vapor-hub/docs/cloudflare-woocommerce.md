# Cloudflare — regras para lojas WooCommerce

> **Esta pasta não vai para o servidor WordPress.**  
> O deploy (`npm run wp:deploy`) envia apenas:
> - `themes/vapor-hub/`
> - `plugins/vapor-hub-loja/`
> - (opcional) `setup/` para scripts WP-CLI remotos  
>
> Arquivos em `wp-vapor-hub/docs/` ficam só no repositório Git, para a equipe de desenvolvimento.

---

## Contexto

Lojas com **carrinho/checkout em blocos** dependem da **Store API**:

```
POST /wp-json/wc/store/v1/cart/update-item
POST /wp-json/wc/store/v1/batch
```

Regras que bloqueiam `/wp-json` para visitantes **sem cookie `wordpress_logged_in`** quebram o carrinho para clientes anônimos — o mesmo tipo de regra **Rest Api Base** visto em zonas Cloudflare na frente de WooCommerce.

**Logado funciona, visitante não** → sintoma clássico de bloqueio REST/WAF, não de bug no tema.

---

## Princípio

| Abordagem | Anti-bot | Risco na loja |
|-----------|----------|---------------|
| Bloquear `/wp-json` para visitantes | Média | **Alto** |
| Bloquear rotas específicas + rate limit | **Alta** | Baixo (com exceção Store API) |
| Proteger wp-login, xmlrpc, enumeração | **Alta** | Nenhum |

---

## Pacote A — Site corporativo (ex.: `newalliance.tech`)

Site institucional **sem loja**. Pode ser mais restritivo.

### 1. Bloquear XML-RPC

**Action:** Block

```
(http.host eq "newalliance.tech")
and (http.request.uri.path eq "/xmlrpc.php")
```

### 2. Bloquear enumeração de usuários via REST

**Action:** Block

```
(http.host eq "newalliance.tech")
and (
  starts_with(http.request.uri.path, "/wp-json/wp/v2/users")
  or http.request.uri.path contains "/wp-json/oembed/"
)
and (not http.cookie contains "wordpress_logged_in")
```

### 3. Rate limit em wp-login

Em **Security → WAF → Rate limiting rules** (não Custom Block):

- **Path:** `/wp-login.php`
- **Requests:** 5 por minuto por IP
- **Action:** Block por 10 minutos

### 4. Proteger wp-json no corporativo (versão segura)

Só no host corporativo; **nunca** em subdomínio de loja:

```
(http.host eq "newalliance.tech")
and (http.request.uri.path contains "/wp-json")
and (not starts_with(http.request.uri.path, "/wp-json/wc/store/"))
and (http.user_agent ne "WordPress")
and (not http.cookie contains "wordpress_logged_in")
and (not (ip.src eq SEU_IP_SERVIDOR or ip.src eq SEU_IP_IPV6))
```

Substitua os IPs pelos do servidor/hospedagem (WP-CLI, cron, integrações).

---

## Pacote B — Loja WooCommerce (clientes)

Aplicar na zona do cliente ou no subdomínio de staging.

### 1. Liberar Store API (Skip — prioridade máxima)

**Nome:** `WooCommerce Store API`  
**Action:** Skip  
**Order:** First (ou antes de qualquer regra que toque em `/wp-json`)

**Expression:**

```
(starts_with(http.request.uri.path, "/wp-json/wc/store/"))
```

**Marcar em “WAF components to skip”:**

- All managed rules
- All Super Bot Fight Mode Rules
- All rate limiting rules
- Browser Integrity Check (se disponível)

### 2. Bloquear XML-RPC

```
(http.request.uri.path eq "/xmlrpc.php")
```

Opcionalmente limitar ao host da loja:

```
(http.host eq "loja.exemplo.com.br")
and (http.request.uri.path eq "/xmlrpc.php")
```

### 3. Bloquear enumeração de usuários (visitante)

```
(starts_with(http.request.uri.path, "/wp-json/wp/v2/users"))
and (not http.cookie contains "wordpress_logged_in")
```

### 4. Rate limit — wp-login

- **Path:** `/wp-login.php`
- **5 req/min/IP** → Block 10 min

### 5. Rate limit — REST genérico (anti-bot efetivo)

**Rate limiting rule** (não block cego em todo `/wp-json`):

**Match:**

```
(http.request.uri.path contains "/wp-json")
and (not starts_with(http.request.uri.path, "/wp-json/wc/store/"))
and (not http.cookie contains "wordpress_logged_in")
```

**Threshold sugerido:** 30–60 requests / 1 min / IP  
**Action:** Managed Challenge ou Block

Bots que varrem posts/pages levam block; carrinho continua livre.

---

## Ordem recomendada das regras

1. **Skip** Store API (`/wp-json/wc/store/`)
2. Rate limits (login, REST genérico)
3. Block xmlrpc / enumeração de usuários
4. Regra agressiva de wp-json **somente no site corporativo** (Pacote A)

---

## O que não fazer em lojas

- Bloquear `/wp-json` inteiro para visitantes
- Copiar a regra **Rest Api Base** do corporativo para subdomínio/cliente sem adaptar
- Super Bot Fight Mode global sem exceção para Store API
- Esquecer de testar **aba anônima** após deploy de regras

---

## Checklist ao subir loja para cliente

1. [ ] Pacote B na zona Cloudflare do domínio final (ou staging)
2. [ ] Regra Skip para `/wp-json/wc/store/` ativa e com checkboxes marcados
3. [ ] Teste anônimo: adicionar ao carrinho → `/carrinho/` → +/- quantidade → remover item
4. [ ] Teste anônimo: checkout até pagamento (ou sandbox)
5. [ ] Confirmar que regra corporativa (`newalliance.tech`) **não** afeta subdomínios de loja — use `http.host eq "newalliance.tech"` na regra agressiva

---

## Teste rápido (linha de comando)

Visitante simulado — Store API deve responder JSON, não HTML do Cloudflare:

```bash
curl -sS -o /dev/null -w "POST update-item: %{http_code}\n" \
  -X POST "https://SEU-DOMINIO/wp-json/wc/store/v1/cart/update-item?_locale=site" \
  -H "Content-Type: application/json" \
  -H "User-Agent: Mozilla/5.0 Chrome/126" \
  -d '{"key":"test","quantity":1}'
```

- **401/409** com JSON → chegou ao WooCommerce (ok para teste sem nonce/carrinho)
- **403** com HTML “Attention Required! | Cloudflare” → ainda bloqueado na borda

Rotas que **devem** continuar acessíveis:

```bash
curl -sS -o /dev/null -w "wc-ajax: %{http_code}\n" \
  -X POST "https://SEU-DOMINIO/?wc-ajax=get_refreshed_fragments"
```

---

## Migração para domínio do cliente

Quando a loja sair de `*.newalliance.tech` para o domínio definitivo:

1. Criar/replicar o **Pacote B** na zona Cloudflare do cliente
2. **Não** aplicar regras do site corporativo New Alliance na loja
3. Repetir checklist de teste anônimo
4. Remover regras de exceção temporárias criadas só para o subdomínio de staging, se houver

---

## Referência — sintoma vs causa

| Sintoma | Causa provável |
|---------|----------------|
| Logado OK, visitante 403 no carrinho | WAF bloqueia `POST /wp-json/wc/store/*` sem cookie de login |
| Erro “Ocorreu um problema” nos blocos | Mesmo — Store API bloqueada |
| Console: `batch 403` ou `update-item 403` | Cloudflare ou WAF na borda (corpo HTML, não JSON) |
| Tema/plugin | Improvável se REST genérico e wc-ajax respondem; tema não hooka Store API |

---

*New Alliance Tecnologia — uso interno. Última revisão: jun/2026.*
