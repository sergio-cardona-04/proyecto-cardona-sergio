FROM node:22-bookworm-slim AS build-css

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY src/ ./src/
RUN npm run build:css

FROM nginx:1.28-alpine

COPY nginx.conf /etc/nginx/conf.d/default.conf

COPY src/index.html /usr/share/nginx/html/index.html
COPY src/app.js /usr/share/nginx/html/app.js

COPY --from=build-css \
    /app/dist/styles.css \
    /usr/share/nginx/html/styles.css

EXPOSE 80
