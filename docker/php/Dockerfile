FROM php:8.3-cli-alpine

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/backend

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "/var/www/backend"]
