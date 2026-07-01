# syntax=docker/dockerfile:1

# ---- Stage 1: Build the SPA ----
FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
# API is served same-origin under /api/v1 by the nginx in this image.
ENV VITE_API_BASE_URL=/api/v1
RUN npm run build

# ---- Stage 2: Nginx (serves SPA + proxies /api to PHP-FPM) ----
FROM nginx:1.27-alpine AS runtime
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
