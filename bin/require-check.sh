#!/usr/bin/env sh
#
# Composer require checker: fails when src/ uses a symbol that is not provided
# by a package listed in the "require" section of composer.json.
#
set -e

CRC_VERSION=4.24.0
TOOL_DIR=build/require-check
PHAR="$TOOL_DIR/composer-require-checker-$CRC_VERSION.phar"
TREE="$TOOL_DIR/tree"

mkdir -p "$TOOL_DIR"

if [ ! -f "$PHAR" ]; then
    echo "require-check: downloading ComposerRequireChecker $CRC_VERSION"
    curl -sSLf -o "$PHAR" \
        "https://github.com/maglnet/ComposerRequireChecker/releases/download/$CRC_VERSION/composer-require-checker.phar"
fi

# CRC resolves symbols from the packages named in "require", locating them in
# vendor/. A normal install pulls orchestra/testbench -> laravel/framework,
# which `replace`s every illuminate/* package this library requires, so those
# packages are never physically present and CRC reports all of their symbols as
# unknown. `composer update --no-dev` does not help: it still resolves the dev
# graph, so laravel/framework still wins and the illuminate/* splits are never
# selected. Building a tree with require-dev removed is what puts the real
# illuminate/* packages on disk.
rm -rf "$TREE"
mkdir -p "$TREE"
cp composer.json composer-require-checker.json "$TREE/"
cp -R src "$TREE/"

php -r '
$file = $argv[1];
$json = json_decode(file_get_contents($file), true);
unset($json["require-dev"], $json["scripts"], $json["config"]["allow-plugins"]);
file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
' "$TREE/composer.json"

composer update --working-dir="$TREE" --no-interaction --no-progress --quiet

XDEBUG_MODE=off
export XDEBUG_MODE

exec php "$PHAR" check \
    --config-file="$TREE/composer-require-checker.json" \
    "$TREE/composer.json"
