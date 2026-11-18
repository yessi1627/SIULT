# Imagen de los servicios Node (gateway, notificaciones y calendario). Es un solo Dockerfile con el
# nombre del servicio como argumento, porque los tres comparten el mismo package-lock (npm workspaces).
#   docker build -t siult/calendario --build-arg SERVICIO=calendario servicios
#
# Construccion en dos etapas (multi-stage): la primera instala dependencias y la segunda copia solo lo
# necesario para ejecutar. La imagen final no lleva pruebas, herramientas de desarrollo ni el codigo
# de los otros servicios.

FROM node:22-alpine AS dependencias
ARG SERVICIO
WORKDIR /app
COPY package.json package-lock.json ./
COPY gateway/package.json gateway/
COPY notificaciones/package.json notificaciones/
COPY calendario/package.json calendario/
RUN npm ci --omit=dev --workspace=${SERVICIO} --no-audit --no-fund

FROM node:22-alpine
ARG SERVICIO
ENV NODE_ENV=production
WORKDIR /app
COPY --from=dependencias /app/node_modules ./node_modules
COPY ${SERVICIO}/package.json ${SERVICIO}/
COPY ${SERVICIO}/src ${SERVICIO}/src
WORKDIR /app/${SERVICIO}

# No ejecuto como root dentro del contenedor
USER node

# Docker revisa la salud del servicio con su propio /salud (PUERTO lo define cada servicio)
HEALTHCHECK --interval=15s --timeout=3s --start-period=10s --retries=3 \
  CMD wget -qO- "http://127.0.0.1:${PUERTO}/salud" > /dev/null || exit 1

CMD ["node", "src/index.js"]
