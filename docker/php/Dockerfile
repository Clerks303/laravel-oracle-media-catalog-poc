FROM php:8.3-fpm-bookworm

ENV ORACLE_HOME=/opt/oracle/instantclient
ENV LD_LIBRARY_PATH=$ORACLE_HOME

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libaio1 libzip-dev unixodbc-dev \
        libpng-dev libonig-dev libxml2-dev curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Oracle Instant Client (basiclite + sdk)
ARG IC_VERSION=21.13.0.0.0dbru
RUN mkdir -p /opt/oracle && cd /opt/oracle \
    && curl -fsSLo basic.zip https://download.oracle.com/otn_software/linux/instantclient/2113000/instantclient-basiclite-linux.x64-${IC_VERSION}.zip \
    && curl -fsSLo sdk.zip   https://download.oracle.com/otn_software/linux/instantclient/2113000/instantclient-sdk-linux.x64-${IC_VERSION}.zip \
    && unzip -q basic.zip && unzip -q sdk.zip \
    && mv instantclient_21_13 instantclient \
    && rm basic.zip sdk.zip \
    && echo /opt/oracle/instantclient > /etc/ld.so.conf.d/oracle-instantclient.conf \
    && ldconfig

# OCI8 PHP extension
RUN echo 'instantclient,/opt/oracle/instantclient' | pecl install oci8-3.3.0 \
    && docker-php-ext-enable oci8

# PDO_OCI
RUN docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/opt/oracle/instantclient,21 \
    && docker-php-ext-install pdo_oci \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
