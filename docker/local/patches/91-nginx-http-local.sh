#!/bin/bash
# LOCAL ONLY — nginx da imagem DockerPress assume Cloudflare/HTTPS.
# Neste stack a porta 8083 é HTTP puro.

for conf in /etc/nginx/conf.d/wordpress.conf /etc/nginx/conf.d/wordpress-admin-pool.conf; do
  if [ -f "$conf" ]; then
    sed -i 's/fastcgi_param HTTPS "on";/fastcgi_param HTTPS "off";/g' "$conf"
    sed -i 's/fastcgi_param SERVER_PORT 443;/fastcgi_param SERVER_PORT 80;/g' "$conf"
  fi
done

echo "✓ Nginx local HTTP (sem simular Cloudflare HTTPS)"
