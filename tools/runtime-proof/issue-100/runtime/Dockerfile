FROM php:8.4.19-fpm-bookworm@sha256:7b0f2acae67bd1678a12abcb359023c4e4fd168a87f4704f5a75c96f0a5406d5
RUN apt-get update && apt-get install -y --no-install-recommends nginx python3 ca-certificates libssl-dev && rm -rf /var/lib/apt/lists/*
RUN pecl install -D 'enable-sockets="no" enable-openssl="no" enable-mysqlnd="no" enable-swoole-curl="no" enable-cares="no" enable-brotli="no" enable-swoole-pgsql="no" enable-swoole-sqlite="no" enable-swoole-thread="no"' swoole-6.2.3 && docker-php-ext-enable swoole && rm -rf /tmp/pear
COPY php.ini /usr/local/etc/php/conf.d/zz-proof.ini
COPY fpm.conf /usr/local/etc/php-fpm.d/zz-proof.conf
COPY nginx.conf /etc/nginx/nginx.conf
CMD ["php", "-v"]
