#!/bin/sh
chown www-data:www-data /var/www/logs
chmod 770 /var/www/logs
exec "$@"
