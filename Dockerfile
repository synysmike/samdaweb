FROM dunglas/frankenphp:php8.3

# =========================
# System dependencies
# =========================
RUN apt-get update && apt-get install -y \
    bash \
    coreutils \
    git \
    && rm -rf /var/lib/apt/lists/*

# =========================
# PHP extensions
# =========================
RUN install-php-extensions \
    bcmath \
    exif \
    ftp \
    gd \
    gmp \
    intl \
    mbstring \
    mysqli \
    opcache \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    xml \
    xsl \
    zip \
    @composer

# Copy Caddyfile
COPY Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /var/www/html
EXPOSE 80 443
