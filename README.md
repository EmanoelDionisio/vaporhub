# Vapor Hub

Hub comercial de e-commerce WordPress/WooCommerce para o nicho de **vape, pod e e-líquido**. Desenvolvido por [New Alliance Tecnologia](https://newalliance.tech).

Não é a CIA do Vapor nem o Piscou Afundou. A CIA serve só de referência de vitrine. O catálogo de produção entra pelo **Tiny ERP**.

## O que tem neste repositório

- Tema filho `vapor-hub` (Hello Elementor)
- Plugin Minha Loja `vapor-hub-loja`
- Setup WooCommerce (categorias/atributos do nicho)
- CSV de **amostra** em `wp-vapor-hub/setup/` — não é o pipeline de produção
- Protótipo React em `src/` (referência de layout)
- Testes PHP e Playwright

## WordPress

Ver [`wp-vapor-hub/README.md`](wp-vapor-hub/README.md). Catálogo: [`docs/catalogo-tiny.md`](docs/catalogo-tiny.md). Mapa de nomes: [`docs/mapa-nomenclatura.md`](docs/mapa-nomenclatura.md).

## React (protótipo)

```bash
cd vapor-hub
npm install
npm run dev
```

Abra `http://localhost:3000`.

## Testes PHP

```bash
npm run test
npm run test:lint
```

## Deploy

Não rode deploy deste piloto ainda. Copie `.env.wp-deploy.example` quando o host/FTP/SSH forem passados.

---

Vapor Hub — pods, e-líquidos e acessórios. PIX, parcelamento e envio para o Brasil.
