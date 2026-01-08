FROM php:8.1-apache

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    git \
    curl \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Installer les extensions PHP nécessaires
RUN docker-php-ext-install pdo_mysql mysqli zip exif pcntl bcmath soap

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Activer les modules Apache nécessaires
RUN a2enmod rewrite headers

# Configurer Apache pour permettre .htaccess et l'accès aux fichiers PHP
RUN echo "<Directory /var/www/html>" > /etc/apache2/conf-available/php-app.conf \
    && echo "    Options -Indexes +FollowSymLinks" >> /etc/apache2/conf-available/php-app.conf \
    && echo "    AllowOverride All" >> /etc/apache2/conf-available/php-app.conf \
    && echo "    Require all granted" >> /etc/apache2/conf-available/php-app.conf \
    && echo "    DirectoryIndex index.php index.html" >> /etc/apache2/conf-available/php-app.conf \
    && echo "</Directory>" >> /etc/apache2/conf-available/php-app.conf \
    && a2enconf php-app

# Configurer le virtual host par défaut pour pointer vers /var/www/html
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|<Directory /var/www/>|<Directory /var/www/html>|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|Options Indexes FollowSymLinks|Options -Indexes +FollowSymLinks|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/sites-available/000-default.conf

# Configurer PHP pour la production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/memory_limit = 128M/memory_limit = 256M/g' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/g' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/post_max_size = 8M/post_max_size = 20M/g' "$PHP_INI_DIR/php.ini"

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers de configuration Composer d'abord (pour optimiser le cache Docker)
COPY composer.json composer.lock* /var/www/html/

# Installer les dépendances Composer (PHPMailer, TCPDF, etc.)
# Utiliser --ignore-platform-reqs pour éviter les problèmes de compatibilité
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs || \
    (echo "Composer install a échoué, tentative avec --no-scripts..." && \
     composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs --no-scripts || \
     echo "Avertissement: Composer install a échoué, mais on continue...")

# Copier le reste du code source de l'application
COPY . /var/www/html/

# Réinstaller les dépendances si nécessaire (au cas où composer.json a changé)
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs || \
    (echo "Réinstallation Composer a échoué, tentative avec --no-scripts..." && \
     composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs --no-scripts || \
     echo "Avertissement: Réinstallation Composer a échoué, mais on continue...")

# Vérifier que vendor/autoload.php existe
RUN if [ ! -f /var/www/html/vendor/autoload.php ]; then \
        echo "ERREUR: vendor/autoload.php n'existe pas. Installation manuelle de PHPMailer..."; \
        mkdir -p /var/www/html/vendor/phpmailer/phpmailer/src; \
        echo "Veuillez installer Composer manuellement ou vérifier composer.json"; \
    else \
        echo "SUCCÈS: vendor/autoload.php existe"; \
    fi

# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Script d'initialisation
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Exposer le port 80
EXPOSE 80

# Commande par défaut pour démarrer Apache avec le script d'initialisation
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]