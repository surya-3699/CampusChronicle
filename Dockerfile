# Campus Chronicle — high-concurrency stack
#   Apache 2.4 (event MPM)  ->  PHP-FPM (bounded worker pool)  ->  MySQL
# Apache's event MPM keeps 700+ client connections open on a handful of
# threads; PHP-FPM caps how many PHP/DB executions run at once. The in-app
# request gateway (config/gateway.php) does rate limiting + admission control.
# Keep Apache (not nginx): the project's access rules live in .htaccess files.
# Rollback: use Dockerfile.prefork (previous mod_php image).
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        apache2 bash curl ca-certificates rclone libzip-dev libonig-dev libcurl4-openssl-dev default-mysql-client util-linux $PHPIZE_DEPS \
 && docker-php-ext-install pdo_mysql mbstring zip opcache curl \
 && ( pecl install apcu && docker-php-ext-enable apcu || echo "APCu unavailable: the gateway falls back to file counters" ) \
 && a2dismod -f mpm_prefork mpm_worker autoindex status 2>/dev/null || true \
 && a2enmod mpm_event proxy proxy_fcgi rewrite headers expires deflate remoteip reqtimeout setenvif \
 && apt-get purge -y --auto-remove $PHPIZE_DEPS \
 && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /var/www/html
COPY . /var/www/html/

COPY deploy/apache.conf        /etc/apache2/sites-available/000-default.conf
COPY deploy/mpm_event.conf     /etc/apache2/mods-available/mpm_event.conf
COPY deploy/campus-tuning.conf /etc/apache2/conf-available/zz-campus-tuning.conf
COPY deploy/php-fpm-pool.conf  /usr/local/etc/php-fpm.d/zzz-campus.conf
COPY deploy/php.ini            /usr/local/etc/php/conf.d/campus.ini

# zz- prefix: conf-enabled loads alphabetically, and this must come after security.conf
# Self-host Bootstrap (pinned). Non-fatal: if the download fails the pages fall back to the CDN.
RUN chmod +x tools/fetch-vendor.sh && (sh tools/fetch-vendor.sh public/vendor || echo "WARNING: vendor download failed; pages will use the CDN")

RUN a2enconf zz-campus-tuning \
 && mv public/uploads /opt/campus-upload-seed && ln -s /var/data/uploads public/uploads \
 && chmod +x deploy/start.sh && mkdir -p /var/data

EXPOSE 10000
CMD ["/var/www/html/deploy/start.sh"]
