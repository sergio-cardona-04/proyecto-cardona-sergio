FROM nginx:1.28-alpine

# Copiar nuestra configuración de Nginx
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copiar los archivos de la página web
COPY src/ /usr/share/nginx/html/

# Puerto interno del contenedor
EXPOSE 80
