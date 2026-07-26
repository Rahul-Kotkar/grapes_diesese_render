FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install MySQLi extension for PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

# Copy all project files into Apache web root
COPY . /var/www/html/

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
