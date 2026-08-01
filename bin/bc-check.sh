#!/usr/bin/env sh
#
# Backwards compatibility check: compares the public API of HEAD against the
# last SemVer tag and fails if a BC break is introduced.
#
set -e

TOOL_DIR=build/bc-check
BIN="$TOOL_DIR/vendor/bin/roave-backward-compatibility-check"

# The checker compares HEAD against the last SemVer tag and hard-fails with
# "Could not detect any released versions" when the repository has none. Skip
# until this package cuts its first release, otherwise there is no baseline.
if [ -z "$(git tag --list '[0-9]*.[0-9]*.[0-9]*')" ]; then
    echo "bc-check: no SemVer release tags found, nothing to compare against. Skipping."
    exit 0
fi

# roave/backward-compatibility-check cannot live in require-dev: it needs
# symfony/console ^7.4, while Laravel 13 installs symfony/console 8.x. Install
# it into an isolated (gitignored) directory so the two graphs stay separate.
# The version is deliberately unconstrained so Composer resolves the newest
# release compatible with the running PHP, which differs across 8.3 - 8.5.
if [ ! -x "$BIN" ]; then
    echo "bc-check: installing roave/backward-compatibility-check into $TOOL_DIR"
    mkdir -p "$TOOL_DIR"
    composer require --working-dir="$TOOL_DIR" \
        roave/backward-compatibility-check --no-interaction --no-progress
fi

exec "$BIN" "$@"