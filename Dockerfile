FROM php:8.4-apache

COPY API /var/www/html/API
COPY webhooks /var/www/html/webhooks
COPY index.php /var/www/html/index.php
COPY php-includes /var/www/html/php-includes
COPY booking.js /var/www/html/booking.js
COPY bootstrap-modified.css /var/www/html/bootstrap-modified.css
COPY cache /var/www/cache
COPY automation /var/www/automation

# make cache directory and overdue logs writable by apache
RUN chmod 770 /var/www/cache && chgrp www-data /var/www/cache
RUN chmod 770 /var/www/automation/logs && chgrp www-data /var/www/automation/logs

# install production php ini
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# install composer and PHPMailer
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    cron \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer require phpmailer/phpmailer

# copy crontab into container for automation
COPY crontab* /etc/cron.d/my-cron
RUN chmod 0644 /etc/cron.d/my-cron
RUN touch /var/log/cron.log

# start the container with cron and apache running
CMD service cron start && apache2-foreground
