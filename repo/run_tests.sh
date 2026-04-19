#!/usr/bin/env bash
# CampusLearn — Test Orchestration
# Runs all test suites in docker-first order.
# Usage: bash run_tests.sh [--suite=<unit|api|frontend-unit|e2e>]

set -euo pipefail

SUITE="${1:-all}"
PASS=0
FAIL=0

log() { echo "[run_tests] $*"; }
pass() { log "PASS: $1"; PASS=$((PASS + 1)); }
fail() { log "FAIL: $1"; FAIL=$((FAIL + 1)); }

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
  # Requires frontend and backend services already running: docker compose up -d frontend backend mysql
  docker compose run --rm e2e \
    npx playwright test e2e/ \
    && pass "frontend-e2e" || fail "frontend-e2e"
}

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
