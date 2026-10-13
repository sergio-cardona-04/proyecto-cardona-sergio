# ETAPA 1: Compilar Tailwind
FROM node:22-bookworm-slim AS build-css

WORKDIR /app

# Instalar dependencias
COPY package.json package-lock.json ./

RUN npm ci

# Copiar el código del frontend
COPY src/ ./src/

# Generar el CSS compilado
RUN npm run build:css


# ETAPA 2: Servir la aplicación con Nginx
FROM nginx:1.28-alpine

# Configuración del proxy inverso
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Archivos de la aplicación
COPY src/index.html /usr/share/nginx/html/index.html
COPY src/app.js /usr/share/nginx/html/app.js

# CSS generado por Tailwind
COPY --from=build-css \
    /app/dist/styles.css \
    /usr/share/nginx/html/styles.css

EXPOSE 80
