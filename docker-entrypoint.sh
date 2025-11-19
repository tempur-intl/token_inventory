#!/bin/bash
# Startup script for token inventory application

# Copy Apache configuration if it exists
if [ -f /var/www/html/apache-config.conf ]; then
    cp /var/www/html/apache-config.conf /etc/apache2/sites-available/000-default.conf
fi

# Ensure session directory exists and is writable
mkdir -p /tmp/sessions
chown www-data:www-data /tmp/sessions
chmod 700 /tmp/sessions

# Update PHP session configuration
echo "session.save_path = \"/tmp/sessions\"" > /usr/local/etc/php/conf.d/sessions.ini

# Start Apache
exec apache2-foreground
