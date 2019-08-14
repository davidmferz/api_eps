FROM php:7.3.6-apache-stretch
RUN apt-get update -y && apt-get install -y libmcrypt-dev openssl
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app
COPY . /app


RUN composer install

CMD php artisan serve --host=0.0.0.0 --port=80
EXPOSE 80
