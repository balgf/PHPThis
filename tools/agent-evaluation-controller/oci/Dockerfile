# syntax=docker/dockerfile:1@sha256:ecfaec9ed6d810b56388c508f4121597bfbba70d41a6dfeee4d8cad5f295fc32
# These public base identities are pinned; execution separately requires the
# final locally reviewed generation/scoring image IDs in the controller lock.
FROM composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer

FROM php:8.4.19-cli-bookworm@sha256:3ddb5a91b44a1c922538576d73a6e808fb2438d9d2d65b1fc3ffd55619fac2e3 AS toolchain
RUN apt-get update \
    && apt-get install --no-install-recommends -y python3 ca-certificates git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN mkdir -p /candidate /dependencies /opt/phpthis \
    && chmod 0755 /candidate /dependencies /opt/phpthis

FROM toolchain AS scoring
RUN /usr/bin/python3 -c 'import json,platform,subprocess; print(json.dumps({"php_version":"8.4.19","composer_version":"2.8.12","python_version":platform.python_version(),"codex_version":None,"relay_sha256":None},sort_keys=True))' > /opt/phpthis/toolchain.json
USER 65533:65533
WORKDIR /candidate
ENTRYPOINT []
CMD ["/bin/sleep", "infinity"]

FROM toolchain AS codex-download
ARG TARGETARCH
COPY install-codex.py /opt/phpthis/install-codex.py
RUN /usr/bin/python3 /opt/phpthis/install-codex.py "$TARGETARCH"

FROM toolchain AS generation
COPY --from=codex-download /opt/phpthis/codex /usr/local/bin/codex
COPY --from=codex-download /opt/phpthis/rg /usr/local/bin/rg
COPY relay.py /opt/phpthis/relay.py
RUN /usr/bin/python3 -c 'import hashlib,json,platform; print(json.dumps({"php_version":"8.4.19","composer_version":"2.8.12","python_version":platform.python_version(),"codex_version":"0.153.1","relay_sha256":hashlib.sha256(open("/opt/phpthis/relay.py","rb").read()).hexdigest()},sort_keys=True))' > /opt/phpthis/toolchain.json
USER 65534:65534
WORKDIR /candidate
ENTRYPOINT []
CMD ["/usr/bin/python3", "/opt/phpthis/relay.py"]
