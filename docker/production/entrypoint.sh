#!/bin/sh
set -eu
umask 027
role="${1:-}"
shift
case "$role" in
    web)
        case "${EPR_PUBLIC_HTTP_PORT:-}" in ''|*[!0-9]*) echo 'Public HTTP port required.' >&2; exit 2 ;; esac
        test "$EPR_PUBLIC_HTTP_PORT" -ge 1 && test "$EPR_PUBLIC_HTTP_PORT" -le 65535 || { echo 'Public HTTP port refused.' >&2; exit 2; }
        # Preserve nginx's effective host protection; the public port comes from configuration.
        sed '/^fastcgi_param[[:space:]]*HTTP_HOST[[:space:]]/d' /etc/nginx/fastcgi_params > /tmp/epr-fastcgi-params
        sed "s/__PUBLIC_HTTP_PORT__/$EPR_PUBLIC_HTTP_PORT/g" /etc/nginx/nginx.conf > /tmp/nginx.conf
        exec nginx -c /tmp/nginx.conf -g 'daemon off;'
        ;;
    php|horizon)
        test "${APP_ENV:-}" = production && test "${APP_DEBUG:-}" = false || { echo 'Production environment required.' >&2; exit 2; }
        test -n "${APP_KEY:-}" || { echo 'Application key required.' >&2; exit 2; }
        php -r '
        require "vendor/autoload.php";
        $key = getenv("APP_KEY");
        $decoded = str_starts_with($key, "base64:") ? base64_decode(substr($key, 7), true) : $key;
        if (!is_string($decoded) || !Illuminate\Encryption\Encrypter::supported($decoded, "AES-256-CBC")) {
            fwrite(STDERR, "Application key format refused.\n"); exit(2);
        }
        '
        # Create only disposable writable runtime directories, never migrate or generate a key.
        mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
        php artisan config:cache --no-interaction >/tmp/config-cache.log 2>&1 || { echo 'Configuration cache failed; inspect the private runtime log.' >&2; exit 6; }
        case "$role" in
            php) exec php-fpm ;;
            horizon) exec php artisan horizon ;;
        esac
        ;;
    scheduler)
        test "${APP_ENV:-}" = production && test "${APP_DEBUG:-}" = false || { echo 'Production environment required.' >&2; exit 2; }
        mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
        php artisan config:cache --no-interaction >/tmp/config-cache.log 2>&1 || { echo 'Configuration cache failed; inspect the private runtime log.' >&2; exit 6; }
        exec php artisan schedule:work --no-interaction
        ;;
    reverb)
        test "${APP_ENV:-}" = production && test "${APP_DEBUG:-}" = false || { echo 'Production environment required.' >&2; exit 2; }
        test -n "${REVERB_APP_ID:-}" && test -n "${REVERB_APP_KEY:-}" && test -n "${REVERB_APP_SECRET:-}" || { echo 'Reverb configuration required.' >&2; exit 2; }
        mkdir -p bootstrap/cache storage/framework/cache/data storage/logs
        php artisan config:cache --no-interaction >/tmp/config-cache.log 2>&1 || { echo 'Configuration cache failed; inspect the private runtime log.' >&2; exit 6; }
        exec php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
        ;;
    *) echo 'Unknown image role.' >&2; exit 2 ;;
esac
