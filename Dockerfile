FROM php:8.0-cli

MAINTAINER Vitalii Zhuk <v.zhuk@fivelab.org>

RUN \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        git ssh-client \
        zip unzip

# Install additional php extensions
RUN \
    apt-get install -y --no-install-recommends \
    yes | pecl install xdebug && \
    docker-php-ext-enable xdebug

# Install composer
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

WORKDIR /code
