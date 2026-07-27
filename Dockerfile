FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install system dependencies, MySQL extensions, and Python 3 ML runtime
RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    python3 \
    python3-pip \
    python3-numpy \
    python3-scipy \
    python3-joblib \
    python3-sklearn \
    && docker-php-ext-install mysqli pdo pdo_mysql pdo_pgsql pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Install PHP dependencies (like PHPMailer)
RUN composer install --no-dev --optimize-autoloader

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to serve from root and allow .htaccess overrides
RUN echo '<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

# Expose port 80
EXPOSE 80
