# Checklist de performance — implementação

Use ao adicionar páginas, componentes, uploads, scripts ou estilos no Vapor Hub.

## 1. Escopo da página

- [ ] Identificar template/rota: home, loja, PDP, cart, checkout, painel Minha Loja, REST público.
- [ ] Listar assets **realmente usados** nesta rota (não copiar enqueue global “por precaução”).

## 2. Imagens

- [ ] Definir perfil `VH_Media_Profiles` se dimensão/aspecto é previsível.
- [ ] Registrar `add_image_size` se front precisa de variantes menores (srcset).
- [ ] Painel: `data-crop="perfil"` no botão de envio.
- [ ] Front: `wp_get_attachment_image()` ou helper `vh_logo_imagem_html()` / `vh_hero_picture_slide()`.
- [ ] Atributo `sizes` coerente com CSS (medir largura real no mobile ~781px).
- [ ] LCP: `eager` + `fetchpriority="high"` + preload se for hero/logo header.
- [ ] Demais imagens: `loading="lazy"` + `decoding="async"`.

## 3. CSS

- [ ] Arquivo novo só enfileirado onde necessário (`is_front_page`, `is_woocommerce`, etc.).
- [ ] Dependências corretas (`vh-design-system` como base do tema).
- [ ] Bump `VH_VERSION` para cache-bust.
- [ ] Evitar duplicar estilos já em `mvp-layout.css` ou `home-layout.css`.

## 4. JavaScript

- [ ] Preferir vanilla em `theme.js` com `defer`.
- [ ] Script de terceiros pesado → lazy load (padrão Cropper/Turnstile).
- [ ] Sem jQuery no tema filho (WooCommerce/Elementor podem carregar o deles).
- [ ] Eventos de scroll: usar `requestAnimationFrame` + `{ passive: true }` (já no header).
- [ ] Localize dados via `wp_localize_script` no handle `vh-theme`.

## 5. Uploads (admin ou público)

- [ ] Admin: REST `/vh-loja/v1/media/upload` + perfil.
- [ ] Público comunidade: crop cliente + `VH_Comunidade_Envios_Service` + moderação.
- [ ] MIME whitelist (jpeg, png, webp) — sem SVG upload público.
- [ ] Turnstile em formulários anônimos quando ativo em Segurança.

## 6. WooCommerce / Elementor

- [ ] Cards produto: size `vh-produto-card`, `sizes` no loop.
- [ ] Não dequeue CSS Woo na home se houver `[products]` ou grid custom.
- [ ] Elementor: CSS por post (`post-*.css`) — difícil remover; aceitar ou isolar páginas.

## 7. Pós-implementação

- [ ] `php -l` nos PHP alterados.
- [ ] Teste visual: home mobile, loja, popup revenda/comunidade, painel upload.
- [ ] PageSpeed mobile (opcional antes do deploy).
- [ ] Deploy via skill `wp-deploy-vapor-hub`.
- [ ] Reenviar mídia legada (PNG, hero teste) se pipeline de imagem mudou.

## 8. Quando usar WP Rocket

Instalar **depois** de otimizações estruturais no tema/plugin.

Rocket cuida de: cache, minify, combine, defer em massa, critical CSS, browser cache headers.

Manter no código: perfis crop, lazy popups, preload LCP, srcset, segurança uploads.
