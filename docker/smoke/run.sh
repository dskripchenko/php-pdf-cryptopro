#!/bin/bash
# Inside the image: installs phpcades' test GOST certificate into the CSP,
# installs the package from /pkg and runs smoke.php against it.
set -euo pipefail
cd /opt/phpcades
bash tests/scripts/setup-root.sh >/dev/null
bash tests/scripts/setup-leaf.sh >/dev/null
bash tests/scripts/setup-crl.sh >/dev/null
THUMB=$(certmgr -list -store uMy | grep -A20 phpcades-test | grep -m1 -iE 'SHA1 (Thumbprint|отпечаток)' | sed 's/.*: *//')
echo "test certificate: $THUMB"

rm -rf /work && mkdir /work && cp -r /pkg/src /pkg/composer.json /work/
cd /work && composer install -q --no-dev --no-interaction
THUMB="$THUMB" ROOT=/opt/phpcades/tests/fixtures/phpcades-root.cer AUTOLOAD=/work/vendor/autoload.php OUT=/out php /pkg/docker/smoke/smoke.php
