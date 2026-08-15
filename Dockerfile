FROM php:8.2-apache

RUN a2enmod rewrite
RUN echo "FallbackResource /index.php" >> /etc/apache2/apache2.conf

COPY . /var/www/html/
