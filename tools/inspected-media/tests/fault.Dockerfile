ARG INSPECTOR_IMAGE
FROM ${INSPECTOR_IMAGE}
COPY tests/fault.php /fault.php
COPY tests/memory-fault.php /memory-fault.php
ENTRYPOINT ["php", "-n", "-d", "display_errors=0", "-d", "log_errors=0", "-d", "memory_limit=-1", "/fault.php"]
