<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\ModuleFile;
use JesseGall\CodeCommandments\Cs\Node;
use PHPUnit\Framework\TestCase;

/**
 * The bridge and the engine against a real C# codebase: every file compiles without a syntax error,
 * every node lies inside its parent, and every identifier's byte span spells its name — the proof that
 * the offsets are UTF-8 bytes on real source, not UTF-16 code units. `CS_CORPUS=<dir>` points it at
 * another codebase to prove one before calibrating on it. Skipped where the corpus or `dotnet` is not.
 */
final class CorpusTest extends TestCase
{
    private const string CORPUS = '/projects/worldwatchmarket/dullahan';

    public function test_every_file_reads_whole_and_in_place(): void
    {
        $root = getenv('CS_CORPUS') ?: (string) getenv('HOME') . self::CORPUS;

        if (! is_dir($root) || Bridge::located()->isNone()) {
            $this->markTestSkipped('needs ~/projects/worldwatchmarket/dullahan and the dotnet SDK');
        }

        $modules = Codebase::scan($root)->modules();

        $this->assertNotSame([], $modules);
        $this->assertSame([], array_values(array_map(static fn (ModuleFile $module): string => $module->file, array_filter($modules, static fn (ModuleFile $module): bool => $module->errors > 0))), 'these files hold a syntax error');
        $this->assertSame([], array_merge(...array_map(self::strays(...), $modules)), 'these nodes reach outside their parent');
        $this->assertSame([], array_merge(...array_map(self::misplaced(...), $modules)), 'these identifiers do not span their own name');
    }

    /**
     * Every node of $module that reaches outside its parent.
     *
     * @return list<string>
     */
    private static function strays(ModuleFile $module): array
    {
        $strays = [];

        foreach (self::tree($module->root) as $node) {
            foreach ($node->children as $child) {
                if ($child->start < $node->start || $child->end > $node->end) {
                    $strays[] = "{$module->file}:{$module->lineAt($child->start)} {$child->kind}";
                }
            }
        }

        return $strays;
    }

    /**
     * Every identifier of $module whose bytes are not its name — written as it is, or verbatim (`@event`),
     * the one spelling C# gives a name besides itself.
     *
     * @return list<string>
     */
    private static function misplaced(ModuleFile $module): array
    {
        $identifiers = array_filter(self::tree($module->root), static fn (Node $node): bool => $node->is('IdentifierName') && $node->name !== null);
        $wrong = array_filter($identifiers, static fn (Node $node): bool => ! in_array(substr($module->source, $node->start, $node->end - $node->start), [$node->name, "@{$node->name}"], true));

        return array_values(array_map(static fn (Node $node): string => "{$module->file}:{$module->lineAt($node->start)} {$node->name}", $wrong));
    }

    /**
     * $node and every node beneath it, expressions included.
     *
     * @return list<Node>
     */
    private static function tree(Node $node): array
    {
        return [$node, ...array_merge([], ...array_map(self::tree(...), $node->children))];
    }
}
