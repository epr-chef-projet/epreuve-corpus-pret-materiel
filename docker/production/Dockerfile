ARG FRONTEND_BUILD_IMAGE=epr-frontend-builder:required

FROM cgr.dev/chainguard/php@sha256:c6714e4394e5fddbf700596f1100e24a9fd8b662398179a70d43e897473c2d8d AS base

FROM cgr.dev/chainguard/wolfi-base@sha256:030e45cb37187dff322bbefe9658bcaec31b824a5d523380771a64864c9f45d4 AS apk-assembly
USER root
COPY --from=base / /base-root
COPY --from=php-packages / /epr-php-packages/
RUN cp /epr-php-packages/build-signing.rsa.pub /base-root/etc/apk/keys/build-signing.rsa.pub \
    && php_upgrades="$(awk -F: '$1 == "P" && $2 ~ /^php-8[.]5($|-)/ && $2 !~ /^php-8[.]5-(redis|imagick|igbinary)($|-)/ { print $2 "=8.5.12-r0" }' /base-root/lib/apk/db/installed)" \
    && apk add --no-cache --no-scripts --root /base-root --repository /epr-php-packages $php_upgrades \
      php-8.5=8.5.12-r0 php-8.5-fpm=8.5.12-r0 \
      php-8.5-bcmath=8.5.12-r0 php-8.5-exif=8.5.12-r0 php-8.5-fileinfo=8.5.12-r0 \
      php-8.5-intl=8.5.12-r0 php-8.5-mbstring=8.5.12-r0 php-8.5-pcntl=8.5.12-r0 \
      php-8.5-pdo_pgsql=8.5.12-r0 php-8.5-posix=8.5.12-r0 php-8.5-zip=8.5.12-r0 \
      php-8.5-dom=8.5.12-r0 php-8.5-xml=8.5.12-r0 php-8.5-xmlwriter=8.5.12-r0 \
      php-8.5-simplexml=8.5.12-r0 php-8.5-redis=6.3.0-r6 php-8.5-imagick=3.8.1-r8 \
      imagemagick-7=7.1.2.33-r0 ttf-dejavu=2.37-r9 \
      nginx-mainline=1.31.6-r3 nginx-mainline-config=1.31.6-r3 \
      nginx-mainline-fastcgi-params=1.31.6-r3 nginx-mainline-mime-types=1.31.6-r3 \
      nginx-mainline-scgi-params=1.31.6-r3 nginx-mainline-uwsgi-params=1.31.6-r3 \
      busybox=1.38.0-r3 curl=8.23.0-r1 libcurl-openssl4=8.23.0-r1 \
      ca-certificates=20260909-r3 ca-certificates-bundle=20260909-r3 \
    && rm /base-root/etc/apk/keys/build-signing.rsa.pub \
    && ldconfig -r /base-root \
    && chroot /base-root /usr/bin/busybox --install -s /usr/bin

# Copy the assembled root once so removed package files stay removed.
FROM scratch AS runtime
USER root
COPY --from=apk-assembly /base-root /
RUN addgroup -g 10001 app \
    && adduser -D -H -u 10001 -G app app
COPY docker/production/php.ini /etc/php/conf.d/zz-production.ini
COPY docker/production/php-fpm.conf /etc/php/php-fpm.conf
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/imagick/policy.xml /etc/epr-imagick/policy.xml
RUN sed '/^<!DOCTYPE policymap>$/d' /etc/epr-imagick/policy.xml > /etc/ImageMagick-7/policy.xml \
    && cp /etc/ImageMagick-7/policy.xml /etc/epr-imagick/policy.xml
ENV PATH=/usr/local/sbin:/usr/local/bin:/usr/bin:/usr/sbin:/sbin:/bin \
    SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt \
    MAGICK_CONFIGURE_PATH=/etc/epr-imagick \
    APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr LOG_LEVEL=info
WORKDIR /var/www/html

FROM runtime AS composer
RUN mkdir -p /usr/local/bin \
    && curl -fsSL https://getcomposer.org/download/2.10.3/composer.phar -o /usr/local/bin/composer \
    && php -r 'exit(hash_file("sha256", "/usr/local/bin/composer") === "7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6" ? 0 : 1);' \
    && chmod 0755 /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME=/tmp/composer

FROM ${FRONTEND_BUILD_IMAGE} AS frontend
USER root
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader
COPY package.json package-lock.json ./
RUN npm ci --no-progress
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-interaction --optimize \
    && composer contracts:generate \
    && APP_URL=http://build.invalid npm run build

FROM composer AS dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --no-interaction --optimize \
    && composer check-platform-reqs --no-dev

FROM runtime AS assembled
COPY --from=dependencies /var/www/html/app/ app/
COPY --from=dependencies /var/www/html/bootstrap/app.php /var/www/html/bootstrap/providers.php bootstrap/
COPY --from=dependencies /var/www/html/config/ config/
COPY --from=dependencies /var/www/html/routes/ routes/
COPY --from=dependencies /var/www/html/database/migrations/ database/migrations/
COPY --from=dependencies /var/www/html/vendor/ vendor/
COPY --from=dependencies /var/www/html/artisan /var/www/html/composer.json /var/www/html/composer.lock ./
COPY --from=dependencies /var/www/html/scripts/epr-probe.php scripts/epr-probe.php
COPY --from=dependencies /var/www/html/resources/views/ resources/views/
COPY --from=frontend /var/www/html/public/ public/
COPY docker/production/InstrumentSans-OFL.txt /usr/share/licenses/instrument-sans/OFL.txt
COPY docker/production/entrypoint.sh /usr/local/bin/epr-image
RUN mkdir -p bootstrap/cache storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R 10001:10001 bootstrap/cache storage \
    && chmod 0755 /usr/local/bin/epr-image \
    && test ! -e public/hot && test ! -e .env \
    && test "$(id -u app)" = 10001
# The assembled filesystem retains APK metadata and the exact candidate's provenance.
FROM scratch AS production
ARG SOURCE_SHA
ARG BUILD_RUN=local
ARG BUILD_ATTEMPT=1
ARG RECIPE_SHA256
COPY --from=assembled / /
LABEL org.opencontainers.image.source="https://github.com/epr-chef-projet/epreuve-corpus-pret-materiel" \
    org.opencontainers.image.revision="$SOURCE_SHA" \
    org.epr.build-run="$BUILD_RUN" org.epr.build-attempt="$BUILD_ATTEMPT" \
    org.epr.recipe-sha256="$RECIPE_SHA256" \
    org.opencontainers.image.base.name="cgr.dev/chainguard/php" \
    org.opencontainers.image.base.digest="sha256:c6714e4394e5fddbf700596f1100e24a9fd8b662398179a70d43e897473c2d8d"
ENV MAGICK_CONFIGURE_PATH=/etc/epr-imagick \
    APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr LOG_LEVEL=info
WORKDIR /var/www/html
USER 10001:10001
EXPOSE 8080 9000
ENTRYPOINT ["epr-image"]
CMD ["php"]
