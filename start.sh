#!/bin/bash
set -e

# Railway injects $PORT — default to 80 for local
PORT=${PORT:-80}
echo "[start.sh] Starting on PORT=$PORT"

# ── Fix Apache ports ────────────────────────────────────────
# Try Debian/Ubuntu-style paths first, then RHEL/Nix paths
for PORTS_CONF in \
    /etc/apache2/ports.conf \
    /etc/httpd/conf/httpd.conf; do
    if [ -f "$PORTS_CONF" ]; then
        sed -i "s/Listen 80$/Listen $PORT/g" "$PORTS_CONF"
        echo "[start.sh] Updated $PORTS_CONF"
        break
    fi
done

# ── Fix VirtualHost port in site config ─────────────────────
for VHOST_CONF in \
    /etc/apache2/sites-available/000-default.conf \
    /etc/httpd/conf.d/000-default.conf; do
    if [ -f "$VHOST_CONF" ]; then
        sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:$PORT>/g" "$VHOST_CONF"
        echo "[start.sh] Updated $VHOST_CONF"
        break
    fi
done

# ── Start Apache ─────────────────────────────────────────────
exec apache2ctl -D FOREGROUND
