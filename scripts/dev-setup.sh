#!/usr/bin/env bash
#
# Reproducible toolchain setup for environments without root access.
#
# Installs PHP 8.5 (satisfies Laravel 13's ^8.3 constraint) and Composer into
# /tmp/pms and /tmp/tools using micromamba, then installs project dependencies.
#
# Usage: bash scripts/dev-setup.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PREFIX="${PHP_PREFIX:-/tmp/pms}"
COMPOSER="${COMPOSER_BIN:-/tmp/tools/composer.phar}"

if ! command -v php >/dev/null 2>&1 && [ ! -x "${PREFIX}/bin/php" ]; then
  echo "==> Installing PHP 8.5 via micromamba"
  mkdir -p /tmp/tools
  curl -sSL -o /tmp/mm.tar.bz2 https://micro.mamba.pm/api/micromamba/linux-64/latest
  mkdir -p /tmp/mmx
  tar -xjf /tmp/mm.tar.bz2 -C /tmp/mmx
  MAMBA_ROOT_PREFIX=/tmp/mamba /tmp/mmx/bin/micromamba create -y -p "${PREFIX}" -c conda-forge 'php=8.5'
fi

export PATH="${PREFIX}/bin:${PATH}"

if [ ! -f "${COMPOSER}" ]; then
  echo "==> Installing Composer"
  mkdir -p "$(dirname "${COMPOSER}")"
  curl -sSL -o "${COMPOSER}" https://getcomposer.org/composer-stable.phar
fi

echo "==> Installing API dependencies"
( cd "${ROOT}/api" && php "${COMPOSER}" install --no-interaction --prefer-dist )

echo "==> Preparing the API environment"
( cd "${ROOT}/api" && [ -f .env ] || cp .env.example .env
  php artisan key:generate --force
  php artisan migrate --seed --force
  php artisan storage:link || true )

echo "==> Installing frontend dependencies"
( cd "${ROOT}/web" && npm install )

echo
echo "Done. Start the services with:"
echo "  cd api && php artisan serve --host=0.0.0.0 --port=8000"
echo "  cd web && npm run dev"
