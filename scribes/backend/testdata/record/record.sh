#!/usr/bin/env bash
# Records every PHP source PHP's backend scribe tests read into ../scribe-cases.jsonl.gz, with what each test's scribe
# makes of it: the tests run as they are, their Codebase::fromString swapped for the recorder's.
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../../../.." && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
tests=("$root"/tests/Scribes/Backend/*.php
    "$root/tests/Detectors/Backend/Spatie/DataCollectionTypeDetectorTest.php"
    "$root/tests/Detectors/Backend/Spatie/HookMissingComputedDetectorTest.php"
    "$root/tests/Detectors/Backend/Spatie/RedundantEnumUnwrapDetectorTest.php")
for test in "${tests[@]}"; do
    sed -e 's/Codebase::fromString(/\\CodeCommandments\\Record\\Recorder::fromString(/g' "$test" > "$work/$(basename "$test")"
done
cat > "$work/bootstrap.php" <<PHP
<?php
require '$root/vendor/autoload.php';
require '$here/Recorder.php';
require '$work/ScribeTestCase.php';
register_shutdown_function(static function (): void {
    \$lines = array_map(static fn (array \$case): string => json_encode(\$case, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), array_values(\CodeCommandments\Record\Recorder::\$cases));
    file_put_contents('$here/../scribe-cases.jsonl.gz', gzencode(implode("\n", \$lines) . "\n", 9));
});
PHP
"$root/vendor/bin/phpunit" --bootstrap "$work/bootstrap.php" "$work"
