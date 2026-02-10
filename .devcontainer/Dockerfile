FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm

RUN set -eux; \
    echo 'Acquire::Retries "5";' > /etc/apt/apt.conf.d/80-retries; \
    apt-get -o Acquire::ForceIPv4=true update; \
    apt-get -o Acquire::ForceIPv4=true install -y --no-install-recommends \
        libicu-dev \
        g++ \
        make \
        autoconf \
        pkg-config; \
    docker-php-ext-install intl; \
    rm -rf /var/lib/apt/lists/*
