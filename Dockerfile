# syntax=docker/dockerfile:1.7

# --- Stage: dev runtime (Apache+PHP only; source mounted via compose) ---
FROM php:8.4.21-apache-trixie AS runtime-dev

# Apache modules: brotli (compression), deflate (gzip fallback),
# expires + headers (cache control), rewrite (future-proofing).
RUN a2enmod brotli deflate expires headers rewrite

COPY docker/apache-app.conf /etc/apache2/conf-enabled/app.conf

# Render sets $PORT; default to 80 locally.
RUN sed -ri 's!^Listen 80$!Listen ${PORT}!' /etc/apache2/ports.conf
ENV PORT=80

WORKDIR /var/www/html
EXPOSE 80

# --- Stage: assets build ---
FROM node:24.14.0-alpine AS assets
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@10.33.2 --activate

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY .browserslistrc vite.config.js vite-plugin-hot-file.js ./
COPY assets ./assets
RUN pnpm build

# --- Stage: prod runtime (default; Render builds this) ---
FROM runtime-dev AS runtime
COPY index.php ./
COPY data ./data
COPY templates ./templates
COPY public ./public
COPY --from=assets /app/dist ./dist
