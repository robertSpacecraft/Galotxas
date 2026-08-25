FROM alpine:3.22

RUN apk add --no-cache \
        bash \
        ca-certificates \
        coreutils \
        findutils \
        mariadb-client \
        restic \
        rclone \
    && addgroup -S backup \
    && adduser -S -D -H -G backup backup

COPY backup.sh /usr/local/bin/galotxas-backup

RUN chmod 0555 /usr/local/bin/galotxas-backup

USER backup

ENV HOME=/tmp

ENTRYPOINT ["/usr/local/bin/galotxas-backup"]
CMD ["backup"]
