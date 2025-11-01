# Utiliser l'image de base
FROM php:8.2-apache

# 1. Installer les dépendances système nécessaires
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Installer les extensions PHP correspondantes
# (pdo_mysql, mysqli, zip, gd pour les images, mbstring pour les chaînes, simplexml)
RUN docker-php-ext-install pdo pdo_mysql mysqli zip gd mbstring simplexml

# 3. Installer Composer (le gestionnaire de paquets PHP)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Définir le répertoire de travail
WORKDIR /var/www/html

# 5. Copier UNIQUEMENT composer.json et composer.lock
# (Optimisation du cache : cette étape ne sera re-exécutée que si ces fichiers changent)
COPY composer.json composer.lock ./

# 6. Installer les dépendances PHP
# (--no-dev pour la production, --optimize-autoloader pour la vitesse)
RUN composer install --no-dev --optimize-autoloader

# 7. Copier votre configuration Apache
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# 8. Activer le mod_rewrite (après avoir copié la conf, c'est plus logique)
RUN a2enmod rewrite

# 9. Copier le reste de votre code (MAINTENANT que 'vendor' est installé)
COPY . .

# 10. Appliquer les permissions
# (S'assurer que 'vendor' et tout le reste appartiennent à Apache)
RUN chown -R www-data:www-data /var/www/html

# 11. Fix pour ServerName (vous l'aviez, c'est bien)
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf