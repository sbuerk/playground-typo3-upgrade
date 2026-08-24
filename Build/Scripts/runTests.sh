#!/usr/bin/env bash

#
# TYPO3 upgrade workshop - test and tooling runner.
#
# Mirrors the TYPO3 Core runTests.sh idea, but drives ddev instead of docker
# directly, so participants only need ddev.
#

set -o pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR" || exit 1

SUITE="unit"
EXTRA=""

showHelp() {
    cat <<HELP

  Usage: Build/Scripts/runTests.sh -s <suite> [options]

  Suites:
    -s unit          PHPUnit unit tests
    -s functional    PHPUnit functional tests (needs the database)
    -s tests         unit + functional
    -s scan          Extension Scanner (CLI) over flight_ops
    -s tca           reminder how to reach the TCA migration check
    -s rector        typo3-rector, dry run
    -s rectorFix     typo3-rector, writes changes
    -s fractor       typo3-fractor, dry run
    -s fractorFix    typo3-fractor, writes changes
    -s upgradeList   list the pending upgrade wizards
    -s preflight     scan + rector dry-run + fractor dry-run + tests

  Options:
    -e "<args>"      extra arguments passed to the underlying tool
    -h               this help

  Examples:
    Build/Scripts/runTests.sh -s tests
    Build/Scripts/runTests.sh -s functional -e "--filter BoardingService"
    Build/Scripts/runTests.sh -s preflight

HELP
}

while getopts ":s:e:h" OPT; do
    case ${OPT} in
        s) SUITE=${OPTARG} ;;
        e) EXTRA=${OPTARG} ;;
        h) showHelp; exit 0 ;;
        \?) echo "Unknown option -${OPTARG}"; showHelp; exit 1 ;;
    esac
done

if ! command -v ddev >/dev/null 2>&1; then
    echo "ddev is required: https://ddev.readthedocs.io/"
    exit 1
fi

ddev start >/dev/null 2>&1

runUnit() {
    echo "--- unit tests ---"
    ddev exec "php vendor/bin/phpunit -c Build/phpunit/UnitTests.xml ${EXTRA}"
}

runFunctional() {
    echo "--- functional tests ---"
    ddev exec bash -c "\
        typo3DatabaseDriver=pdo_mysql \
        typo3DatabaseName=funcdb \
        typo3DatabaseUsername=root \
        typo3DatabasePassword=root \
        typo3DatabaseHost=db \
        TYPO3_PATH_ROOT=/var/www/html/public \
        TYPO3_PATH_APP=/var/www/html \
        php vendor/bin/phpunit -c Build/phpunit/FunctionalTests.xml ${EXTRA}"
}

RC=0
case ${SUITE} in
    unit)        runUnit; RC=$? ;;
    functional)  runFunctional; RC=$? ;;
    tests)       runUnit; RC=$?; runFunctional || RC=$? ;;
    scan)
        echo "--- extension scanner ---"
        ddev exec "./vendor/bin/typo3 extension:scan flight_ops --no-progress ${EXTRA}"; RC=$? ;;
    tca)
        cat <<TCA
--- TCA migration check ---
There is no CLI command for this one. Open it in the backend:

  https://t3upgrade-workshop.ddev.site/typo3/
  Admin Tools > Upgrade > Check TCA Migrations

Your functional tests report the same thing - run:
  Build/Scripts/runTests.sh -s functional
TCA
        ;;
    rector)      ddev exec "./vendor/bin/rector process --dry-run ${EXTRA}"; RC=$? ;;
    rectorFix)   ddev exec "./vendor/bin/rector process ${EXTRA}"; RC=$? ;;
    fractor)     ddev exec "./vendor/bin/fractor process --dry-run ${EXTRA}"; RC=$? ;;
    fractorFix)  ddev exec "./vendor/bin/fractor process ${EXTRA}"; RC=$? ;;
    upgradeList) ddev exec "./vendor/bin/typo3 upgrade:list ${EXTRA}"; RC=$? ;;
    preflight)
        echo "=== PRE-FLIGHT CHECK ==="
        ddev exec "./vendor/bin/typo3 extension:scan flight_ops --no-progress" || RC=$?
        ddev exec "./vendor/bin/rector process --dry-run" || RC=$?
        ddev exec "./vendor/bin/fractor process --dry-run" || RC=$?
        runUnit || RC=$?
        runFunctional || RC=$?
        echo "=== PRE-FLIGHT CHECK DONE (rc=${RC}) ==="
        ;;
    *) echo "Unknown suite: ${SUITE}"; showHelp; exit 1 ;;
esac

exit ${RC}
