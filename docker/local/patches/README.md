# Patches só do stack local

O entrypoint HTTP (`entrypoint.sh`) **não** fica versionado aqui: é o fork já usado em `Sistemas/dockerpress/stacks/local/patches/`. O script `scripts/docker-local-up.sh` copia esse arquivo para este diretório antes do `compose up`.

`90-nginx-security-headers.sh` é a versão sem HSTS, para o browser não forçar HTTPS na porta 8083.

Produção / piloto online **não** usa esta pasta — continua FTP/SSH + imagem DockerPress publicada.
