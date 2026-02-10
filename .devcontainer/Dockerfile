FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm

RUN set -eux; \
    # Disable Yarn repo (not needed, causes GPG failures)
    rm -f /etc/apt/sources.list.d/yarn.list || true; \
    rm -f /etc/apt/trusted.gpg.d/yarn.gpg || true; \
    echo 'Acquire::Retries "5";' > /etc/apt/apt.conf.d/80-retries; \
    apt-get -o Acquire::ForceIPv4=true update; \
    apt-get -o Acquire::ForceIPv4=true install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        g++ \
        make \
        autoconf \
        pkg-config; \
    docker-php-ext-install intl zip; \
    rm -rf /var/lib/apt/lists/*
