FROM php:8.2-fpm as production
# Configurar el directorio de trabajo
WORKDIR /var/www/html
# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    zip \
    git \
    curl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl pdo pdo_mysql mbstring exif pcntl bcmath gd zip
# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# Copiar archivos de la aplicación a la imagen
COPY . /var/www/html
RUN composer install --optimize-autoloader --no-dev
# Establecer permisos para el almacenamiento y el caché
RUN chmod -R 777 /var/www/html/storage
# Crear el enlace simbólico para la carpeta storage
RUN  php artisan storage:link
# Establecer permisos para el almacenamiento y el caché
RUN chmod -R 775 /var/www/html/public/storage
# Limpiamos caché
RUN  php artisan config:cache  && php artisan route:cache && php artisan view:cache
# Exponer el puerto 9000 para PHP-FPM
EXPOSE 9000
# Comando por defecto
CMD ["php-fpm"]
