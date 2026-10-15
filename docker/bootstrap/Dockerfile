FROM php:8.5.11-cli-trixie@sha256:01a109229f4465bc9ef042d9198f09a4d9da7775a825dbd8572dcdbd8756c4d3

  RUN apt-get update \
      && apt-get install -y --no-install-recommends ca-certificates curl git unzip \
      && rm -rf /var/lib/apt/lists/*

  RUN set -eu; \
      curl --fail --show-error --silent --location \
          https://getcomposer.org/download/2.10.3/composer.phar \
          --output /usr/local/bin/composer; \
      printf '%s  %s\n' \
          '7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6' \
          '/usr/local/bin/composer' \
          | sha256sum --check --strict -; \
      chmod 0755 /usr/local/bin/composer

  ENV COMPOSER_HOME=/tmp/epr-composer

  WORKDIR /app