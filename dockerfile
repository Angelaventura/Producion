FROM php:8.1-apache

WORKDIR /var/www/html
COPY . .

# Crear y configurar permisos para la carpeta data
RUN mkdir -p data && \
    chmod -R 777 data

# Habilitar el módulo rewrite de Apache
RUN a2enmod rewrite

# Configurar Apache para usar el puerto asignado por Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE ${PORT}

CMD ["apache2-foreground"]
