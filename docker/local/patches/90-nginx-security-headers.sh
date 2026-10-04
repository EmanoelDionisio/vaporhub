#!/bin/bash
# LOCAL ONLY — sem HSTS para o browser não forçar HTTPS na porta 8083.

configure_nginx_security_headers() {
  SECURITY_HEADERS_CONF="/etc/nginx/conf.d/security-headers.conf"

  cat > "$SECURITY_HEADERS_CONF" <<'SECHEADEOF'
# Vapor Hub local — HTTP
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
SECHEADEOF

  echo "✓ Security headers (local HTTP — sem HSTS)"
}
