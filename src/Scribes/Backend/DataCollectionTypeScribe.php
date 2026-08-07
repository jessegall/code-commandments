<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Docblock;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;

/**
 * Retypes a `Data` property typed `DataCollection` to `array` — ONLY when the element is already stated on
 * the AST via `#[DataCollectionOf(X::class)]`, so the collection stays element-typed (an element living
 * only in a `@var` is left for a hand-fix; the resolver reads the AST, never docblock text). The fix
 * carries the whole DECLARATION: the `@param DataCollection<K, X> $x` documenting it is re-headed to
 * `@param array<K, X> $x`, and the `use …\DataCollection;` import goes once nothing in the class spells
 * the name — a rewrite that contradicts its own docblock is not a fix.
 */
final class DataCollectionTypeScribe extends RepentScribe
{
    private const string DATA_COLLECTION = 'Spatie\\LaravelData\\DataCollection';

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);
        $retyped = [];

        foreach ($findings as $match) {
            if ($match instanceof NodeMatch && ($match->node instanceof Param || $match->node instanceof Property) && $this->fix($draft, $match)) {
                $retyped[$match->file->path] = $match;
            }
        }

        // One import question per FILE, asked after every declaration in it has been answered.
        foreach ($retyped as $match) {
            $this->dropDeadImport($draft, $match);
        }

        return $draft->rewrites();
    }

    /**
     * Retype one declaration and its docblock, reporting whether anything was rewritten — the import
     * is only reconsidered for a file the scribe actually touched.
     */
    private function fix(Draft $draft, NodeMatch $match): bool
    {
        $node = $match->node;
        $typeName = self::dataCollectionNameIn($node->type);

        // Only rewrite when `#[DataCollectionOf]` already names the element — else retyping to `array`
        // would drop the element typing, so leave it for a hand-fix.
        if ($typeName === null || ! self::carriesDataCollectionOf($node)) {
            return false;
        }

        $writer = Writer::for($draft, $match);
        $writer->replace($typeName, 'array');
        $this->retypeDocblock($writer, $match);

        return true;
    }

    /**
     * Re-head the tag that documents THIS declaration. A promoted constructor property is documented
     * on the constructor (`@param DataCollection<int, X> $content`), a plain property on itself
     * (`@var`) — so both blocks are offered the name, and only that name's tag moves. Every sibling
     * tag, the prose, and the spacing stay exactly as the author wrote them.
     */
    private function retypeDocblock(Writer $writer, NodeMatch $match): void
    {
        $name = self::nameOf($match->node);

        if ($name === null) {
            return;
        }

        foreach ([$match->node, $match->parent()->node] as $documented) {
            $text = $documented?->getDocComment()?->getText();

            if ($text !== null && ($retypedText = Docblock::retype($text, $name, self::DATA_COLLECTION, 'array')) !== $text) {
                $writer->replaceDocblock($documented, $retypedText);
            }
        }
    }

    /**
     * Drop the import once the class has nothing left to spell with it. The question is asked of the
     * file as the fix LEAVES it, not as it sits on disk: every declaration this scribe retypes counts
     * as already changed, and anything it skipped still holds the import.
     */
    private function dropDeadImport(Draft $draft, NodeMatch $match): void
    {
        $class = $match->enclosingClass();

        if ($class !== null && ! self::stillNamesDataCollection($class)) {
            Writer::for($draft, $match)->dropImport(self::DATA_COLLECTION);
        }
    }

    private static function stillNamesDataCollection(ClassLike $class): bool
    {
        foreach (self::declarationsIn($class) as $declaration) {
            if (self::dataCollectionNameIn($declaration->type) !== null && ! self::carriesDataCollectionOf($declaration)) {
                return true;
            }
        }

        foreach ([$class, ...$class->stmts] as $node) {
            $text = $node instanceof Node ? $node->getDocComment()?->getText() : null;

            if ($text !== null && Docblock::mentionsType(self::asFixed($text, $class), self::DATA_COLLECTION)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A docblock as the fix leaves it — every tag the scribe re-heads already re-headed, so a block
     * whose ONLY mention of the type is one the fix removes doesn't keep the import alive.
     */
    private static function asFixed(string $text, ClassLike $class): string
    {
        foreach (self::declarationsIn($class) as $declaration) {
            $name = self::nameOf($declaration);

            if ($name !== null && self::dataCollectionNameIn($declaration->type) !== null && self::carriesDataCollectionOf($declaration)) {
                $text = Docblock::retype($text, $name, self::DATA_COLLECTION, 'array');
            }
        }

        return $text;
    }

    /**
     * Every typed declaration in the class — promoted constructor params and plain properties alike.
     *
     * @return list<Param|Property>
     */
    private static function declarationsIn(ClassLike $class): array
    {
        $declarations = [];

        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Property) {
                $declarations[] = $stmt;
            }

            if ($stmt instanceof ClassMethod) {
                $declarations = [...$declarations, ...$stmt->params];
            }
        }

        return $declarations;
    }

    /**
     * The variable name a declaration carries — `$content` for a param, the first declared property
     * otherwise. Null when it has none to speak of (a destructured or dynamic form).
     */
    private static function nameOf(Node $declaration): ?string
    {
        if ($declaration instanceof Param) {
            return AstNode::variableNameOf($declaration->var) !== null ? $declaration->var->name : null;
        }

        return $declaration instanceof Property ? $declaration->props[0]->name->toString() : null;
    }

    /**
     * The `DataCollection` Name node inside a bare / nullable / union type, or null.
     */
    private static function dataCollectionNameIn(?Node $type): ?Name
    {
        if ($type instanceof Name) {
            return ltrim($type->toString(), '\\') === self::DATA_COLLECTION ? $type : null;
        }

        if ($type instanceof NullableType) {
            return self::dataCollectionNameIn($type->type);
        }

        if ($type instanceof UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Name && ltrim($member->toString(), '\\') === self::DATA_COLLECTION) {
                    return $member;
                }
            }
        }

        return null;
    }

    private static function carriesDataCollectionOf(Param|Property $node): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = ltrim($attr->name->toString(), '\\');

                if ($name === 'DataCollectionOf' || str_ends_with($name, '\\DataCollectionOf')) {
                    return true;
                }
            }
        }

        return false;
    }
}
