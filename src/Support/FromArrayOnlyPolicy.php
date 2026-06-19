<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Throwable;

/**
 * The single source of truth for "is this Data class UNSAFE to give the
 * FromArrayOnly trait?" — so the trait-add (DataClassFromArrayOnly) and the
 * from([])->make() rewrite (ExplicitDataFactory) agree on the SAME classes.
 *
 * A class is trait-unsafe when EITHER:
 *  - it has an object / unknown `::from()` site anywhere ({@see DataFromSiteCensus}); or
 *  - it carries a Spatie magic attribute (#[Computed]/#[MapName]/#[LoadRelation]/
 *    #[MapInputName]) — it needs the magic from(Model) path, so the array-only
 *    assert (and therefore make()) would break it.
 *
 * Emitting `make()` for such a class calls a method the withheld trait never
 * defined (#65/#70/#72), so the rewrite must be gated on this.
 */
final class FromArrayOnlyPolicy
{
    /** @var array<string, array<string, true>> */
    private static array $cache = [];

    /**
     * @param  list<string>  $suffixes
     * @return array<string, true>  short class name => true (trait-unsafe)
     */
    public static function traitUnsafeShortNames(CodebaseIndex $index, array $suffixes): array
    {
        $files = [];

        foreach ($index->classes() as $summary) {
            $files[$summary->filePath] = true;
        }

        $paths = array_keys($files);
        sort($paths);
        $key = md5(implode('|', $paths) . '#' . implode(',', $suffixes));

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $unsafe = DataFromSiteCensus::objectFromShortNames($index, $suffixes);

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;

        foreach ($paths as $file) {
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            try {
                $ast = $parser->parse($content);
            } catch (Throwable) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
                if ($class->name !== null && SpatieDataMagic::classHasMagicAttribute($class)) {
                    $unsafe[$class->name->toString()] = true;
                }
            }
        }

        return self::$cache[$key] = $unsafe;
    }
}
