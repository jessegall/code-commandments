<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;

/**
 * Surface docblocks that have grown beyond a short narrative sentence.
 *
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static maxNarrativeLines(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.14.0')]
class LongDocblockProphet extends PhpCommandment
{
    private const DEFAULT_MAX_NARRATIVE_LINES = 3;

    public function description(): string
    {
        return 'Keep docblocks to one short narrative sentence above the @-tag block';
    }

    /**
     * Enums are exempt: {@see EnumCaseMustBeDocumentedProphet} endorses
     * documenting every case as a `{@see Enum::Case}: …` bullet in the enum's
     * class docblock, which is necessarily multi-line. `UnitEnum` matches every
     * enum by kind.
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        return [\UnitEnum::class];
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Docblocks describe what a class, method, or property is — terse, scoped,
present tense. They are not for explaining how the code works internally,
listing rules it enforces, walking through orchestration order, or
narrating implementation details. The code does that.

One short sentence is the target. Anything more than one or two
sentences is almost always wrong. The prophet warns when a docblock:

  - contains a numbered or bulleted list
  - has a multi-line narrative above the @-tag block
  - spans multiple paragraphs of prose

The narrative above the @-tags is what gets measured. @param, @return,
and @throws lines are structured metadata and are ignored — repeat them
freely.

Bad (a tutorial dressed as a docblock):

    /**
     * Broadcasts publishable changes to writable+mirroring siblings.
     *
     * The five rules, applied in order on every dispatch:
     *   1. Suppression - bulk import middleware brackets entire imports...
     *   2. Master gate - when a shop has a master-source channel...
     *   3. Origin skip - the channel that originated the change...
     */

Good:

    /**
     * Broadcasts publishable changes to writable+mirroring siblings.
     */

If you find existing docs that break this — historical narration, stale
references, plan-phase mentions, multi-paragraph ceremony, numbered rule
lists — rewrite them inline with whatever else you're changing.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        // Generated scaffold/skill code carries deliberate teaching docblocks —
        // the doc-length rule is about hand-written narrative, not generated docs.
        if (str_contains($content, '@code-commandments-generated')) {
            return $this->righteous();
        }

        $maxLines = (int) $this->config('max_narrative_lines', self::DEFAULT_MAX_NARRATIVE_LINES);
        $warnings = [];

        foreach ($this->collectDocumentedNodes($ast) as $entry) {
            $warning = $this->inspectDocblock($entry['node'], $entry['label'], $maxLines);
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        if (empty($warnings)) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * Walk the AST and collect every class/method/property carrying a docblock.
     *
     * @param  array<Node>  $ast
     * @return array<array{node: Node, label: string}>
     */
    private function collectDocumentedNodes(array $ast): array
    {
        $entries = [];

        foreach ($this->findNodes($ast, Node\Stmt\Class_::class) as $class) {
            $className = $class->name?->toString() ?? 'anonymous class';
            $entries[] = ['node' => $class, 'label' => "class {$className}"];
            $this->collectClassMembers($class, $className, $entries);
        }

        foreach ($this->findNodes($ast, Node\Stmt\Interface_::class) as $interface) {
            $name = $interface->name?->toString() ?? 'interface';
            $entries[] = ['node' => $interface, 'label' => "interface {$name}"];
            $this->collectClassMembers($interface, $name, $entries);
        }

        foreach ($this->findNodes($ast, Node\Stmt\Trait_::class) as $trait) {
            $name = $trait->name?->toString() ?? 'trait';
            $entries[] = ['node' => $trait, 'label' => "trait {$name}"];
            $this->collectClassMembers($trait, $name, $entries);
        }

        foreach ($this->findNodes($ast, Node\Stmt\Enum_::class) as $enum) {
            $name = $enum->name?->toString() ?? 'enum';
            $entries[] = ['node' => $enum, 'label' => "enum {$name}"];
            $this->collectClassMembers($enum, $name, $entries);
        }

        return $entries;
    }

    /**
     * @param  Node\Stmt\Class_|Node\Stmt\Interface_|Node\Stmt\Trait_|Node\Stmt\Enum_  $classLike
     * @param  array<array{node: Node, label: string}>  $entries
     */
    private function collectClassMembers(Node\Stmt\ClassLike $classLike, string $className, array &$entries): void
    {
        foreach ($classLike->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                $methodName = $stmt->name->toString();
                $entries[] = ['node' => $stmt, 'label' => "{$className}::{$methodName}()"];
                continue;
            }

            if ($stmt instanceof Node\Stmt\Property) {
                $names = array_map(
                    fn (Node\PropertyItem $prop) => '$' . $prop->name->toString(),
                    $stmt->props
                );
                $entries[] = [
                    'node' => $stmt,
                    'label' => "{$className} property " . implode(', ', $names),
                ];
            }
        }
    }

    private function inspectDocblock(Node $node, string $label, int $maxLines): ?Warning
    {
        $doc = $node->getDocComment();
        if ($doc === null) {
            return null;
        }

        $narrative = $this->extractNarrativeLines($doc->getText());

        $listLine = $this->findListLine($narrative);
        if ($listLine !== null) {
            return $this->warningAt(
                $doc->getStartLine() + $listLine['offset'],
                "Docblock for {$label} contains a {$listLine['kind']} list — keep docblocks to one short sentence; move rules and steps into the code or a real doc",
                $listLine['snippet']
            );
        }

        if ($this->hasMultipleParagraphs($narrative)) {
            return $this->warningAt(
                $doc->getStartLine(),
                "Docblock for {$label} spans multiple paragraphs — keep it to one short sentence and let the code carry the detail"
            );
        }

        $lineCount = count($narrative);
        if ($lineCount > $maxLines) {
            return $this->warningAt(
                $doc->getStartLine(),
                "Docblock for {$label} has a {$lineCount}-line narrative (max: {$maxLines}) — aim for one short sentence above the @-tag block"
            );
        }

        return null;
    }

    /**
     * Strip the comment frame and return the narrative lines that appear
     * before the first @-tag. Blank lines are dropped; offsets relative to
     * the docblock start are preserved so warnings can land on the right line.
     *
     * @return array<array{text: string, offset: int}>
     */
    private function extractNarrativeLines(string $docText): array
    {
        $lines = preg_split('/\R/', $docText) ?: [];
        $narrative = [];

        foreach ($lines as $offset => $rawLine) {
            $line = trim($rawLine);

            $line = preg_replace('#^/\*\*+#', '', $line) ?? $line;
            $line = preg_replace('#\*+/$#', '', $line) ?? $line;
            $line = preg_replace('/^\*+\s?/', '', $line) ?? $line;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '@')) {
                break;
            }

            $narrative[] = ['text' => $line, 'offset' => $offset];
        }

        return $narrative;
    }

    /**
     * @param  array<array{text: string, offset: int}>  $narrative
     * @return array{kind: string, offset: int, snippet: string}|null
     */
    private function findListLine(array $narrative): ?array
    {
        foreach ($narrative as $line) {
            if (preg_match('/^\d+[.)]\s+\S/', $line['text']) === 1) {
                return ['kind' => 'numbered', 'offset' => $line['offset'], 'snippet' => $line['text']];
            }

            if (preg_match('/^[-*\x{2022}]\s+\S/u', $line['text']) === 1) {
                return ['kind' => 'bulleted', 'offset' => $line['offset'], 'snippet' => $line['text']];
            }
        }

        return null;
    }

    /**
     * @param  array<array{text: string, offset: int}>  $narrative
     */
    private function hasMultipleParagraphs(array $narrative): bool
    {
        if (count($narrative) < 2) {
            return false;
        }

        for ($i = 1; $i < count($narrative); $i++) {
            if ($narrative[$i]['offset'] - $narrative[$i - 1]['offset'] > 1) {
                return true;
            }
        }

        return false;
    }
}
