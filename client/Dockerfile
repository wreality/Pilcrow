FROM --platform=$BUILDPLATFORM node:16-alpine as build-stage
WORKDIR /app

ARG VERSION
ARG VERSION_URL
ARG VERSION_DATE
COPY package.json yarn.lock ./
RUN yarn
COPY . .
RUN yarn build

FROM nginx:latest

COPY --from=build-stage /app/dist/spa /var/www/html
COPY .docker/default.conf.template /etc/nginx/templates/default.conf.template





