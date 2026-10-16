ARG INSPECTOR_IMAGE
FROM ${INSPECTOR_IMAGE}
USER 0:0
ARG PROBE_MODE
RUN case "$PROBE_MODE" in stdout|stderr|timeout|exit) printf '%s' "$PROBE_MODE" > /probe-mode ;; *) exit 1 ;; esac
COPY tests/probe-fault.php /probe-fault.php
COPY --chmod=0555 tests/probe-fault.sh /usr/local/bin/ffprobe
USER 65534:65534
