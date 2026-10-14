# Panel (Quasar SPA) para producción (Dokploy).
#
# VITE_APP_API_URL se "hornea" en el build (no es runtime): en Dokploy va
# como Build Arg. Cambiarla = volver a desplegar.

FROM node:22-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

ARG VITE_APP_API_URL
ENV VITE_APP_API_URL=$VITE_APP_API_URL

COPY . .
RUN npx quasar build

FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/spa /usr/share/nginx/html
EXPOSE 80
