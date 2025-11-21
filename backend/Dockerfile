# /SavePoint/backend/Dockerfile

FROM alpine:latest

# Puedes cambiar esta versión si sale una nueva
ARG PB_VERSION=0.33.0

RUN apk add --no-cache \
    unzip \
    ca-certificates

# Descargar y descomprimir PocketBase
ADD https://github.com/pocketbase/pocketbase/releases/download/v${PB_VERSION}/pocketbase_${PB_VERSION}_linux_amd64.zip /tmp/pb.zip
RUN unzip /tmp/pb.zip -d /pb/

# Exponer el puerto interno (dentro del contenedor)
EXPOSE 8080

# Arrancar PocketBase escuchando en todas las interfaces dentro del contenedor
CMD ["/pb/pocketbase", "serve", "--http=0.0.0.0:8080"]