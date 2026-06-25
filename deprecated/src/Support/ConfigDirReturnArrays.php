<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Walks every `*.php` file in a config directory, yielding the top-level
 * `return [...]` array node and the file's base name for each one that has
 * a parseable array return.
 */
final class ConfigDirReturnArrays
{
    /**
     * @return iterable<array{0: Node\Expr\Array_, 1: string}>
     */
    public static function each(string $configDir): iterable
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;

        foreach (Glob::paths($configDir . '/*.php') as $file) {
            try {
                $ast = $parser->parse((string) file_get_contents($file));
            } catch (\Throwable) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            $return = $finder->findFirstInstanceOf($ast, Node\Stmt\Return_::class);

            if (! $return instanceof Node\Stmt\Return_ || ! $return->expr instanceof Node\Expr\Array_) {
                continue;
            }

            yield [$return->expr, basename($file, '.php')];
        }
    }
}
