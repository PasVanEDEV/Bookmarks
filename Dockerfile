FROM php:8.3-apache

# mod_php needs exactly one MPM (prefork). Remove any event/worker MPM at the
# filesystem level (a2dismod proved unreliable here) and enable prefork.
# Then honor .htaccess (default AllowOverride None) and enable needed modules.
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
 && a2enmod mpm_prefork rewrite headers \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# App code into the Apache web root.
COPY . /var/www/html/

# Startup: Railway injects $PORT and mounts the persistent volume at $DATA_DIR.
# Make Apache listen on $PORT, ensure the data dir is writable, and print a
# short MPM/config diagnostic to the logs before starting.
RUN printf '%s\n' \
  '#!/bin/sh' \
  'set -e' \
  ': "${PORT:=80}"' \
  'sed -ri "s!^Listen [0-9]+!Listen ${PORT}!" /etc/apache2/ports.conf' \
  'sed -ri "s!<VirtualHost \\*:[0-9]+>!<VirtualHost *:${PORT}>!" /etc/apache2/sites-available/000-default.conf' \
  'if [ -n "$DATA_DIR" ]; then mkdir -p "$DATA_DIR"; chown -R www-data:www-data "$DATA_DIR"; fi' \
  'echo "=== MPM diagnostic ==="' \
  'ls /etc/apache2/mods-enabled/ | grep -i mpm || echo "no mpm symlink"' \
  'apache2ctl -t 2>&1 || true' \
  'echo "listening on port ${PORT}"' \
  'echo "====================="' \
  'exec apache2-foreground' \
  > /usr/local/bin/start.sh \
 && chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
