FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        g++ \
        make \
        autoconf \
        pkg-config \
    && docker-php-ext-install intl \
    && rm -rf /var/lib/apt/lists/*
