# DockerPress local — Vapor Hub

Stack **só deste projeto**. Não usa o Compose em `Sistemas/dockerpress/stacks/local` (porta 8081) nem stacks de produção.

| Item | Valor |
|---|---|
| Imagem | `newalliance/dockerpress:1.28-nginx-dual-php8.3-patch1` |
| HTTP | **http://127.0.0.1:8083** |
| Painel Minha Loja | http://127.0.0.1:8083/minha-loja/ |
| wp-admin | http://127.0.0.1:8083/wp-admin/ |
| Usuário / senha | `admin` / `vaporhub_local` (ver `.env`) |

Portas que **não** tocamos: 8080 Perfex, 8081 DockerPress genérico, 8082 Espaço Sex.

Tema e plugin entram por bind mount do repo (`wp-vapor-hub/`). Alterar código no disco reflete no container.

## Comandos (raiz do vapor-hub)

```bash
npm run wp:local        # sobe, instala Woo/tema/plugin, roda setup + CSV de amostra
npm run wp:local:down   # para os containers (volumes permanecem)
```

Apagar banco e arquivos WP locais:

```bash
docker compose -p vaporhub -f docker/local/docker-compose.yml --env-file docker/local/.env down -v
```

Isso **não** é o deploy FTP/SSH do piloto online.
