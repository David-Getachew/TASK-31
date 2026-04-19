#!/usr/bin/env bash
# CampusLearn — Test Orchestration
# Runs all test suites in docker-first order.
# Usage: bash run_tests.sh [--suite=<unit|api|frontend-unit|e2e>]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

SUITE="${1:-all}"
PASS=0
FAIL=0

DEFAULT_APP_KEY="base64:by5ipvlCVjyDmh4xxNGVm0m3vy3tv4JgAH36EUbMTGs="
DEFAULT_DB_PASSWORD="campuslearn_local"
DEFAULT_MYSQL_ROOT_PASSWORD="campuslearn_root_local"
DEFAULT_BACKUP_ENCRYPTION_KEY="b7b5aa1b9da4d59a7c092c55005b0954dc1d18d2acba9d96256ad0adde12fe49"
DEFAULT_DIAGNOSTIC_ENCRYPTION_KEY="530998018beefe2cf9d3e61ada165dab23315ff683276f879fd3f36af8523d0e"

log() { echo "[run_tests] $*"; }
pass() { log "PASS: $1"; PASS=$((PASS + 1)); }
fail() { log "FAIL: $1"; FAIL=$((FAIL + 1)); }

upsert_env() {
  local file="$1"
  local key="$2"
  local value="$3"

  if grep -qE "^${key}=" "$file"; then
    sed -i "s#^${key}=.*#${key}=${value}#" "$file"
  else
    echo "${key}=${value}" >> "$file"
  fi
}

ensure_env_files() {
  if [[ ! -f .env ]]; then
    log "Creating repo/.env with local defaults"
    cat > .env <<EOF
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=${APP_KEY:-$DEFAULT_APP_KEY}
DB_DATABASE=${DB_DATABASE:-campuslearn}
DB_USERNAME=${DB_USERNAME:-campuslearn}
DB_PASSWORD=${DB_PASSWORD:-$DEFAULT_DB_PASSWORD}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-$DEFAULT_MYSQL_ROOT_PASSWORD}
BACKUP_ENCRYPTION_KEY=${BACKUP_ENCRYPTION_KEY:-$DEFAULT_BACKUP_ENCRYPTION_KEY}
DIAGNOSTIC_ENCRYPTION_KEY=${DIAGNOSTIC_ENCRYPTION_KEY:-$DEFAULT_DIAGNOSTIC_ENCRYPTION_KEY}
EOF
  fi

  if [[ ! -f backend/.env ]]; then
    log "Creating backend/.env from backend/.env.example"
    cp backend/.env.example backend/.env
  fi

  upsert_env backend/.env APP_KEY "${APP_KEY:-$DEFAULT_APP_KEY}"
  upsert_env backend/.env DB_PASSWORD "${DB_PASSWORD:-$DEFAULT_DB_PASSWORD}"
  upsert_env backend/.env MYSQL_ROOT_PASSWORD "${MYSQL_ROOT_PASSWORD:-$DEFAULT_MYSQL_ROOT_PASSWORD}"
  upsert_env backend/.env BACKUP_ENCRYPTION_KEY "${BACKUP_ENCRYPTION_KEY:-$DEFAULT_BACKUP_ENCRYPTION_KEY}"
  upsert_env backend/.env DIAGNOSTIC_ENCRYPTION_KEY "${DIAGNOSTIC_ENCRYPTION_KEY:-$DEFAULT_DIAGNOSTIC_ENCRYPTION_KEY}"
}

ensure_e2e_stack() {
  log "Ensuring e2e service stack is running (mysql, backend, frontend)"
  docker compose up -d --build mysql backend frontend
}

run_backend_unit() {
  log "--- Backend unit tests (repo/backend/unit_tests/) ---"
  docker compose run --rm backend \
    sh -lc 'set +e; failed=0; for f in $(find unit_tests -name "*Test.php" | sort); do echo "[unit] $f"; ./vendor/bin/pest "$f" --colors=always || failed=1; done; exit $failed' \
    && pass "backend-unit" || fail "backend-unit"
}

run_backend_api() {
  log "--- Backend API/integration tests (repo/backend/api_tests/) ---"
  docker compose run --rm backend \
    sh -lc 'set +e; failed=0; for f in $(find api_tests -name "*Test.php" | sort); do echo "[api] $f"; ./vendor/bin/pest "$f" --colors=always || failed=1; done; exit $failed' \
    && pass "backend-api" || fail "backend-api"
}

run_frontend_unit() {
  log "--- Frontend unit tests (repo/frontend/unit_tests/) ---"
  # Uses frontend-test service (node/builder stage); the nginx runtime image has no node
  docker compose run --rm frontend-test \
    npx vitest run unit_tests/ --reporter=verbose \
    && pass "frontend-unit" || fail "frontend-unit"
}

run_frontend_e2e() {
  log "--- Frontend E2E tests (repo/frontend/e2e/) ---"
  ensure_e2e_stack
  docker compose run --rm backend \
    sh -lc 'php artisan migrate --force --no-interaction && php artisan db:seed --class=Database\\Seeders\\E2eTestSeeder --force --no-interaction'
  docker compose run --rm e2e \
    npx playwright test e2e/ \
    && pass "frontend-e2e" || fail "frontend-e2e"
}

ensure_env_files

case "$SUITE" in
  --suite=unit)        run_backend_unit ;;
  --suite=api)         run_backend_api ;;
  --suite=frontend-unit) run_frontend_unit ;;
  --suite=e2e)         run_frontend_e2e ;;
  all)
    run_backend_unit
    run_backend_api
    run_frontend_unit
    run_frontend_e2e
    ;;
  *)
    echo "Usage: bash run_tests.sh [--suite=unit|api|frontend-unit|e2e]"
    exit 1
    ;;
esac

log "========================================="
log "Results: ${PASS} passed, ${FAIL} failed"
log "========================================="

[ "$FAIL" -eq 0 ]
