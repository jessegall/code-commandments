<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Support\Frozen;

/**
 * `commandments freeze <path>` / `unfreeze <path>` — stamp a file as intentionally immutable, or lift the
 * stamp. A frozen file is still scanned but never flagged or rewritten. Idempotent.
 */
final class Freeze implements Command
{
    public function names(): array
    {
        return ['freeze', 'unfreeze'];
    }

    public function run(Input $input): int
    {
        $path = $input->firstArgument();

        if ($path === null) {
            fwrite(STDERR, "Usage: commandments <freeze|unfreeze> <path>\n");

            return 2;
        }

        if (! is_file($path)) {
            fwrite(STDERR, "No such file: {$path}\n");

            return 2;
        }

        return $input->command() === 'unfreeze' ? $this->unfreeze($path) : $this->freeze($path);
    }

    private function freeze(string $path): int
    {
        $source = (string) file_get_contents($path);

        if (str_contains($source, Frozen::FILE_MARKER)) {
            fwrite(STDOUT, "\033[2mAlready frozen: {$path}\033[0m\n");

            return 0;
        }

        file_put_contents($path, $this->stamped($source));
        fwrite(STDOUT, "\033[32m✓ Frozen {$path}\033[0m — scanned for resolution, but never flagged or repented.\n");

        return 0;
    }

    private function unfreeze(string $path): int
    {
        $source = (string) file_get_contents($path);

        if (! str_contains($source, Frozen::FILE_MARKER)) {
            fwrite(STDOUT, "\033[2mNot frozen: {$path}\033[0m\n");

            return 0;
        }

        $lines = array_filter(
            explode("\n", $source),
            static fn (string $line): bool => ! str_contains($line, Frozen::FILE_MARKER),
        );

        file_put_contents($path, implode("\n", $lines));
        fwrite(STDOUT, "\033[32m✓ Unfroze {$path}\033[0m — it is a target again.\n");

        return 0;
    }

    /**
     * Insert the freeze stamp on its own line right after the opening `<?php` line (a comment is legal
     * there, even before a `declare`).
     */
    private function stamped(string $source): string
    {
        $stamp = '// ' . Frozen::FILE_MARKER . ' — deliberately immutable; excluded from code-commandments '
            . 'judging & repent (run `commandments unfreeze` to lift).';

        $firstLineEnd = strpos($source, "\n");

        if ($firstLineEnd === false) {
            return $source . "\n" . $stamp . "\n";
        }

        return substr($source, 0, $firstLineEnd + 1) . $stamp . "\n" . substr($source, $firstLineEnd + 1);
    }
}
