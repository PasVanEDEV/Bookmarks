FROM php:8.3-apache

# Honor .htaccess (default is AllowOverride None) and enable the modules it uses.
RUN a2enmod rewrite headers \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# App code into the Apache web root.
COPY . /var/www/html/

# Startup: Railway injects $PORT and mounts the persistent volume at $DATA_DIR.
# Make Apache listen on $PORT and ensure the data dir is writable by www-data.
RUN printf '%s\n' \
  '#!/bin/sh' \
  'set -e' \
  ': "${PORT:=80}"' \
  'sed -ri "s!^Listen [0-9]+!Listen ${PORT}!" /etc/apache2/ports.conf' \
  'sed -ri "s!<VirtualHost \\*:[0-9]+>!<VirtualHost *:${PORT}>!" /etc/apache2/sites-available/000-default.conf' \
  'if [ -n "$DATA_DIR" ]; then mkdir -p "$DATA_DIR"; chown -R www-data:www-data "$DATA_DIR"; fi' \
  'exec apache2-foreground' \
  > /usr/local/bin/start.sh \
 && chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
