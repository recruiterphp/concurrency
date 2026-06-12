ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    openjdk-21-jre-headless \
    && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions mongodb pcntl

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies including dev dependencies for testing
RUN composer install --optimize-autoloader

# Copy application code
COPY . .

# Set environment variable for Composer
ENV COMPOSER_ALLOW_SUPERUSER=1

CMD ["tail", "-f", "/dev/null"]
