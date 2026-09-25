#!/usr/bin/env bash
# Records every project PHP's ExtractComponentScribeTest builds a Vue codebase from into ../extraction-projects.json:
# the test runs as it is, its Codebase::fromString and Codebase::scan swapped for the recorder's.
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../../../.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
sed -e 's/Codebase::fromString(/\\CodeCommandments\\Record\\Recorder::fromString(/g' \
    -e 's/Codebase::scan(/\\CodeCommandments\\Record\\Recorder::scan(/g' \
    -e 's/final class ExtractComponentScribeTest/final class RecordedExtractComponentScribeTest/' \
    "$root/tests/Scribes/Frontend/ExtractComponentScribeTest.php" > "$work/RecordedExtractComponentScribeTest.php"
cat > "$work/bootstrap.php" <<PHP
<?php
require '$root/vendor/autoload.php';
require '$here/Recorder.php';
register_shutdown_function(static fn () => file_put_contents('$here/../extraction-projects.json', json_encode(\CodeCommandments\Record\Recorder::\$projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"));
PHP
"$root/vendor/bin/phpunit" --bootstrap "$work/bootstrap.php" "$work/RecordedExtractComponentScribeTest.php"
