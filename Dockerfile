FROM php:8.2-apache

# Install required PHP extensions and dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libldap2-dev \
    && docker-php-ext-install curl ldap \
    && a2enmod rewrite ssl headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Copy Apache configuration
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Copy and set permissions for entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create session directory
RUN mkdir -p /tmp/sessions \
    && chown www-data:www-data /tmp/sessions \
    && chmod 700 /tmp/sessions

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 644 /var/www/html/*.php

# Expose port 80
EXPOSE 80

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
