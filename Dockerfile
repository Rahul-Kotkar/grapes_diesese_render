FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install system dependencies and MySQL extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    python3 \
    && docker-php-ext-install mysqli pdo pdo_mysql \
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

# Tune Apache MPM prefork for low memory (512MB RAM free plan)
RUN echo '<IfModule mpm_prefork_module>\n\
    StartServers             2\n\
    MinSpareServers          1\n\
    MaxSpareServers          3\n\
    MaxRequestWorkers       10\n\
    MaxConnectionsPerChild 100\n\
</IfModule>' > /etc/apache2/conf-available/mpm_tuning.conf \
    && a2enconf mpm_tuning

# Expose port 80
EXPOSE 80
