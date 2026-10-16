# PHP CLI with CryptoPro CSP 5, CAdES and phpcades, plus OpenSSL's gost
# engine — to run CryptoProCadesSigner against a real CSP.
#
# CryptoPro CSP cannot be redistributed: download the archive for your
# architecture from https://cryptopro.ru/products/csp/downloads into
# docker/dist/ (see docker/README.md), then:
#
#   docker build -t php-pdf-cryptopro --build-arg CSP_ARCHIVE=linux-arm64_deb.tgz docker
#
# phpcades is built from CryptoPro's sources (github.com/CryptoPro/phpcades),
# as its own Dockerfile does. A fresh CSP install carries a 90-day trial
# licence.
FROM ubuntu:24.04

ARG CSP_ARCHIVE=linux-amd64_deb.tgz
# Pinned: bump deliberately.
ARG PHPCADES_COMMIT=8fe27f0
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        php-cli php-dev php-mbstring php-xml php-zip unzip \
        cmake build-essential libboost-all-dev libxml2-dev git ca-certificates curl \
        libengine-gost-openssl \
    && rm -rf /var/lib/apt/lists/*

# CryptoPro CSP (KC1) with the devel headers, the legacy and CAdES packages phpcades needs.
COPY dist/${CSP_ARCHIVE} /tmp/csp.tgz
RUN mkdir /tmp/csp && tar -xzf /tmp/csp.tgz -C /tmp/csp --strip-components=1 \
    && cd /tmp/csp && chmod +x install*.sh \
    && ./install.sh kc1 lsb-cprocsp-devel cprocsp-legacy cprocsp-pki-cades \
    && rm -rf /var/lib/apt/lists/* /tmp/csp /tmp/csp.tgz

ENV PATH="/opt/cprocsp/bin/aarch64:/opt/cprocsp/bin/amd64:/opt/cprocsp/sbin/aarch64:/opt/cprocsp/sbin/amd64:${PATH}"

RUN git init /opt/phpcades && cd /opt/phpcades \
    && git remote add origin https://github.com/CryptoPro/phpcades.git \
    && git fetch --depth 50 origin main && git checkout --quiet "$PHPCADES_COMMIT" \
    && make \
    && ln -s "$(realpath build/src/libphpcades.so)" "$(php -r 'echo ini_get("extension_dir");')/libphpcades.so" \
    && echo 'extension=libphpcades.so' > "$(php -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')/30-phpcades.ini" \
    && php -r 'exit(class_exists("CPStore") ? 0 : 1);'

# The gost engine for PHP's OpenSSL: php-pdf computes the Streebog digest
# and verifies the CMS the CSP returns.
COPY enable-gost-engine.sh /usr/local/bin/enable-gost-engine.sh
RUN chmod +x /usr/local/bin/enable-gost-engine.sh && enable-gost-engine.sh \
    && php -r 'exit(in_array("md_gost12_256", openssl_get_md_methods(), true) ? 0 : 1);'

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
