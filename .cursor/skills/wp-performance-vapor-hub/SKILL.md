---
name: wp-performance-vapor-hub
description: >-
  Padrões de performance web do projeto Vapor Hub (WordPress,
  WooCommerce, tema filho, plugin Minha Loja): PageSpeed/Lighthouse, LCP/FCP/CLS,
  imagens WebP/crop/srcset, enqueue de CSS/JS, lazy load, Turnstile/Cropper sob
  demanda, uploads públicos. Use ao implementar ou revisar features que afetem
  velocidade, peso de página, scripts, estilos, mídia, hero, logo, produtos,
  categorias, popups, REST público ou otimização antes/depois de cache (WP Rocket).
---

# Performance Web — Vapor Hub

Skill do projeto em `.cursor/skills/wp-performance-vapor-hub/`.

**Site:** `piloto.example` · **Tema filho:** `wp-vapor-hub/themes/vapor-hub` · **Plugin:** `wp-vapor-hub/plugins/vapor-hub-loja`

## Princípios do projeto

1. **Funcionalidade primeiro** — otimizar sem quebrar layout, popups, moderação ou fluxos WooCommerce.
2. **Código antes do plugin de cache** — resolver na origem (tema/plugin); WP Rocket amplifica (minify, combine, page cache).
3. **Uma downscale de qualidade** — crop no tamanho de exibição; WebP 90; evitar dupla compressão desnecessária.
4. **Só carregar o que a página usa** — scripts pesados (Cropper, Turnstile) **sob demanda**, não no `wp_enqueue_scripts` global.
5. **LCP é prioridade na home** — hero: `eager` + `fetchpriority="high"` + preload; nunca `loading="lazy"` no primeiro slide.

## Arquitetura de performance (mapa rápido)

| Camada | Arquivo / classe | Responsabilidade |
|--------|------------------|------------------|
| Helpers front | `themes/.../includes/vh-performance.php` | Preload LCP, logo responsiva, hero `<picture>`, `vhAssets` (URLs lazy) |
| Enqueue tema | `themes/.../functions.php` → `vh_enfileirar_assets()` | CSS por contexto; **sem** Cropper/Turnstile global |
| JS lazy | `themes/.../assets/js/theme.js` | `vhCarregarCropper()`, `vhCarregarTurnstile()`, `vhRenderTurnstile()` |
| Perfis crop | `plugins/.../includes/class-vh-media-profiles.php` | Dimensões fixas por uso (produto, hero, logo…) |
| WebP upload | `plugins/.../includes/class-vh-media.php` | JPEG/PNG → WebP qualidade 90 |
| REST upload admin | `plugins/.../includes/rest/class-vh-rest-media.php` | Crop + perfil + WebP |
| Crop painel | `plugins/.../admin/js/media-crop.js` | Cropper.js só no **Minha Loja** (admin) |

## Perfis de imagem (`VH_Media_Profiles`)

| Slug | Dimensão | Onde |
|------|----------|------|
| `produto` | 800×800 | Imagem principal + galeria produto |
| `comunidade` | 640×800 | Galeria comunidade + envio público |
| `categoria` | 640×800 | Thumbnail categoria |
| `hero-desktop` | 1920×800 | Banner/slides desktop |
| `hero-mobile` | 1080×1350 | Slide mobile |
| `logo` | 400×160 | Logo (header, footer, painel) |

**Painel:** botões com `data-crop="perfil"` → `vh-media-enviar-btn` ou `vh-upload-btn` + `data-crop`.

**Biblioteca WP (sem crop):** escolher imagem existente — não reprocessa.

## Tamanhos WordPress (`add_image_size` em `functions.php`)

| Slug | Uso |
|------|-----|
| `vh-hero` / `vh-hero-mobile` / `vh-hero-mobile-sm` | Hero + srcset mobile |
| `vh-produto-card` / `vh-produto-card-sm` | Cards loja/home |
| `vh-categoria` / `vh-categoria-sm` | Grid categorias |
| `vh-logo` / `vh-logo-2x` | Logo header/footer |
| `vh-comunidade` | Galeria comunidade |

Após novos sizes: regenerar thumbs no servidor (`wp media regenerate`) ou reenviar imagens pelo painel.

## Regras de imagem no front

### Hero (`template-parts/content-hero.php` + `vh_hero_picture_slide()`)

- Primeiro slide: `loading="eager"`, `fetchpriority="high"`.
- Slides seguintes: `loading="lazy"`.
- Mobile: `<source media="(max-width: 781px)">` + `srcset` quando houver attachment ID.
- Preload: `vh_preload_hero_lcp()` em `wp_head` (prioridade 2).
- Dimensões desktop no `<img>`: 1920×800; mobile vem do `<source>`.

### Logo (`vh_logo_imagem_html()`)

- Header: `loading="eager"`, `fetchpriority="high"`, `sizes="(max-width: 781px) 160px, 200px"`.
- Footer: `loading="lazy"`.
- Preferir attachment + WebP; fallback URL se sem ID.
- Reenviar logo pelo crop `logo` após mudanças no pipeline.

### Categorias / produtos

- Usar `wp_get_attachment_image()` ou `$product->get_image()` com **`sizes`** explícito.
- Exemplo produtos home: `(max-width: 480px) 50vw, (max-width: 781px) 33vw, 25vw`.
- Abaixo da dobra: `loading="lazy"` está correto.

### Qualidade crop

- Cliente: Cropper `imageSmoothingQuality: 'high'`, JPEG 0.92.
- Servidor: WebP 90 (`VH_Media::QUALIDADE_WEBP`).

## CSS / JS — enqueue

### CSS do tema (ordem típica)

1. Google Fonts (pesos reduzidos: 400/600/700)
2. Hello Elementor + tema filho + `design-system.css`
3. `home-layout.css` — **só** `is_front_page()`
4. `mvp-layout.css` — global (header, footer, popups)
5. `woocommerce.css` — **necessário na home** (grid de produtos no shortcode)

`preconnect`: `fonts.googleapis.com` + `fonts.gstatic.com` (`vh_preconnect_google_fonts`).

### JS

- `theme.js`: `strategy => defer`, **sem** dependência de Cropper.
- **Não** enfileirar globalmente: Cropper CDN, Turnstile `api.js`.
- Turnstile: `?render=explicit` + `vhRenderTurnstile()` ao abrir popup.
- Cropper: `vhCarregarCropper()` ao selecionar foto (comunidade).

### O que não remover sem testar

- CSS WooCommerce na home (cards de destaques).
- CSS Elementor (`frontend.min.css`, `post-*.css`) — depende das páginas Elementor.
- `wc-blocks.css` em cart/checkout.

`vh_desativar_gutenberg_frontend()` já remove block library fora de cart/checkout.

## Popups e REST público

- Turnstile + nonce REST + rate limit — manter ao otimizar.
- Crop comunidade: cliente + backup servidor `VH_Media_Profiles::aplicar_perfil('comunidade')`.
- Moderação antes de publicar — não expor na galeria automaticamente.

## Checklist ao implementar feature nova

Antes de commitar, percorrer [checklist.md](checklist.md).

Resumo rápido:

- [ ] Novo script/CSS é necessário nesta página? Se não → não enfileirar globalmente.
- [ ] Nova imagem tem perfil crop + size WP + `sizes`/`srcset`?
- [ ] É LCP ou acima da dobra? → eager/preload; senão → lazy.
- [ ] Upload passa por WebP + dimensão máxima do layout?
- [ ] Popup/modal? → carregar Turnstile/Cropper só na interação.
- [ ] Bump `VH_VERSION` (tema) e/ou `VH_LOJA_VERSION` (plugin) se assets mudaram.

## PageSpeed — diagnóstico típico

| Métrica | Foco no nosso código |
|---------|----------------------|
| **LCP alto** | Hero oversized, lazy no LCP, falta preload, imagem teste fora do perfil |
| **Render-blocking** | Muitos CSS (Elementor + tema); lazy scripts; WP Rocket depois |
| **Imagem grande** | Reenviar com perfil correto; `srcset`; regenerar thumbs |
| **TBT 0 / CLS 0** | Manter — não introduzir JS síncrono no head |

## WP Rocket (camada posterior)

Delegar ao plugin: minify/combine CSS+JS, defer adicional, critical CSS automático, cache de página, lazy load avançado, CDN.

Não duplicar no código o que o Rocket fará melhor — preferir hooks nativos do tema (`vh-performance.php`) para lógica estrutural.

## Deploy e validação

- Deploy: skill `wp-deploy-vapor-hub` → `npm run wp:deploy:all`.
- Validar: PageSpeed mobile em URL pública (modo anônimo / sem cache admin).
- Após deploy de sizes novos: reenviar mídia crítica (logo, hero) ou `wp media regenerate`.

## Anti-padrões (evitar)

- Cropper ou Turnstile no `wp_enqueue_scripts` de todas as páginas.
- `loading="lazy"` no hero ou logo do header.
- URL de imagem crua no template quando existe attachment (perde srcset).
- PNG grande para logo sem WebP.
- Upload público sem Turnstile/rate limit/validação MIME.
- Upscaling agressivo (imagem minúscula cropada para 800px) — avisar no painel se possível.
- Quebrar `woocommerce.css` na home removendo estilos dos cards.

## Referência de helpers PHP

```php
vh_attachment_id_from_url( $url )
vh_srcset_value_from_url( $url, $size )
vh_hero_slides_dados()
vh_hero_picture_slide( $slide, $indice )
vh_logo_imagem_html( $args )
vh_logo_attachment_id()
vh_turnstile_ativo()
```

## Referência JS (front)

```javascript
window.vhCarregarCropper()   // Promise
window.vhCarregarTurnstile() // Promise
window.vhRenderTurnstile( el, cfg, 'turnstileWidgetId' )
window.vhAssets              // { cropperCss, cropperJs, turnstileJs }
```
