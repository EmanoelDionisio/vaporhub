# Design System — Loading assíncrono (Minha Loja)

Padrão oficial para telas que carregam dados via REST ou API externa (Tiny ERP, Google SEO, etc.).

**Versão do plugin:** a partir de **3.7.9**  
**CSS:** `admin/css/design-system-loading.css` (handle `vh-ds-loading`)  
**PHP:** `includes/class-vh-ui-skeleton.php`  
**JS:** `paLojaApp.ui` em `admin/js/app.js`

---

## Quando usar

| Situação | Usar |
|----------|------|
| Conteúdo vem de fetch/REST após abrir a tela | **Skeleton** |
| Botão de salvar enviando formulário | Estado `is-carregando` no botão (já existente) |
| Spinner centralizado (`.vh-estado-carregando`) | **Legado — não usar em telas novas** |

O skeleton imita o layout final (tabela, formulário, cards) para reduzir “pulo” visual quando os dados chegam.

---

## PHP — renderizar skeleton

```php
<?php
// Presets disponíveis (constantes VH_UI_Skeleton):
// PRESET_TOOLBAR_TABLE_5 — toolbar + tabela 5 colunas (ex.: mapeamento categorias)
// PRESET_TABLE_3         — tabela 3 colunas
// PRESET_TABLE_4         — tabela 4 colunas
// PRESET_FORM            — labels + inputs (+ botão opcional)
// PRESET_CARDS           — grade de cards

VH_UI_Skeleton::render( 'meu-loading-id', VH_UI_Skeleton::PRESET_TABLE_3, [
    'rows' => 8, // opcional, default por preset
] );
?>

<div id="meu-painel" class="vh-painel-async vh-ui-oculto" hidden>
    <!-- conteúdo real -->
</div>
```

**Regras:**
- ID do skeleton **único** na página
- Painel real com classes `vh-painel-async vh-ui-oculto` + atributo `hidden`
- Escolha preset que **pareça** com o conteúdo final

---

## JavaScript — concluir ou erro

Disponível em `window.paLojaApp.ui`:

```javascript
var ui = window.paLojaApp.ui;

// Sucesso: esconde skeleton, mostra painel
ui.concluirCarregamentoAsync('meu-loading-id', 'meu-painel');

// Ou separado:
ui.esconderSkeleton('meu-loading-id');
ui.mostrarPainelAsync('meu-painel');

// Erro na requisição (mantém área visível com mensagem)
ui.skeletonErro('meu-loading-id', 'Não foi possível carregar os dados.');
```

**Exemplo completo:**

```javascript
rest('minha/rota').then(function (resp) {
    renderConteudo(resp.dados);
    paLojaApp.ui.concluirCarregamentoAsync('meu-loading-id', 'meu-painel');
}).catch(function (err) {
    paLojaApp.ui.skeletonErro('meu-loading-id', err.message);
    paLojaApp.feedback(err.message, 'erro');
});
```

---

## CSS — blocos disponíveis

Compor manualmente se nenhum preset servir:

| Classe | Uso |
|--------|-----|
| `.vh-skeleton-panel` | Container do loading |
| `.vh-skeleton` | Barra com shimmer |
| `.vh-skeleton--line` | Linha de texto |
| `.vh-skeleton--input` | Campo |
| `.vh-skeleton--btn` | Botão |
| `.vh-skeleton--badge` | Badge/status |
| `.vh-skeleton--card` | Card |
| `.vh-skeleton--w-*` | Larguras (20–75%) |
| `.vh-skeleton-table` + `__row--N` | Tabela N colunas |
| `.vh-skeleton-form` | Formulário |
| `.vh-skeleton-toolbar` | Barra superior |
| `.vh-skeleton-cards` | Grid de cards |

**Acessibilidade:** painel com `role="status"`, `aria-busy="true"`, texto `.vh-sr-only` “Carregando…”.

**Motion:** `prefers-reduced-motion` desativa shimmer.

**Ocultação:** sempre usar `vh-ui-oculto` + `hidden` (tema WordPress pode anular só `[hidden]`).

---

## Referência implementada

**Mapeamento Tiny ERP** — `admin/views/integracoes/tiny-mapeamento.php`

| Seção | Preset | Skeleton ID | Painel ID |
|-------|--------|-------------|-----------|
| Categorias | `toolbar-table-5` | `vh-tiny-map-cats-loading` | `vh-tiny-map-categorias` |
| Atributos | `form` | `vh-tiny-map-attrs-loading` | `vh-tiny-map-atributos` |
| Campos | `table-3` | `vh-tiny-map-campos-loading` | `vh-tiny-map-campos` |

---

## Migração de telas legadas

1. Substituir `.vh-estado-carregando` + spinner por `VH_UI_Skeleton::render()`
2. Enfileirar `vh-ds-loading` (já incluso no painel Minha Loja via `class-vh-app.php`)
3. Trocar `innerHTML = 'Carregando…'` por skeleton ou `ui.skeletonErro`
4. Candidatos futuros: `admin/js/seo.js` (métricas Google)

---

## Checklist nova tela async

- [ ] Preset escolhido ou composição CSS documentada
- [ ] Skeleton ID único
- [ ] Painel com `vh-painel-async vh-ui-oculto hidden`
- [ ] `ui.concluirCarregamentoAsync` no `.then`
- [ ] `ui.skeletonErro` no `.catch`
- [ ] Teste com hard refresh e throttling de rede (DevTools)
