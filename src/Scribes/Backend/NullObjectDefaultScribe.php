<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\NullObjectDefault;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Scribes\Draft;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\UnionType;

/**
 * Repents all-nullable Data classes by reshaping `?T = null` fields to `T = <identity>`
 * where the type has an honest Null Object. All-or-nothing per class: if ANY field's type
 * lacks a Null Object, leaves the class untouched.
 */
final class NullObjectDefaultScribe extends RepentScribe implements NeedsCodebase
{
    private ?NullObjectDefault $identities = null;

    public function withCodebase(Codebase $codebase): void
    {
        $this->identities = new NullObjectDefault($codebase);
    }

    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            if ($match instanceof NodeMatch && $match->node instanceof Class_) {
                $this->reshape($draft, $match, $match->node);
            }
        }

        return $draft->rewrites();
    }

    private function reshape(Draft $draft, NodeMatch $match, Class_ $class): void
    {
        if ($this->identities === null) {
            return;
        }

        $source = $match->file->source;
        $edits = [];

        foreach (AstNode::constructorParamsOf($class) as $param) {
            if ($param->flags === 0) {
                continue; // not a promoted property — irrelevant to the DTO's contract
            }

            $written = $this->writtenNonNullType($param->type, $source);

            if ($written === null || ! $this->defaultsToNull($param)) {
                return; // a required or non-object nullable field — can't reshape the class
            }

            $identity = $this->identities->forType(TypeName::nullableClass($param->type), $written);

            if ($identity === null) {
                return; // this field's type has no expressible Null Object — leave the class alone
            }

            $edits[] = [$param->type, $written, $param->default, $identity];
        }

        $writer = Writer::for($draft, $match);

        foreach ($edits as [$type, $written, $default, $identity]) {
            $writer->replace($type, $written);
            $writer->replace($default, $identity);
        }
    }

    /**
     * The source of a nullable type's single non-null part — `?Callback`/`Callback|null` → the
     * `Callback` token exactly as written (already in scope at the field). Null when the type
     * isn't a nullable this scribe reshapes (a bare scalar, an intersection, no null member).
     */
    private function writtenNonNullType(?Node $type, string $source): ?string
    {
        if ($type instanceof NullableType) {
            return $this->slice($source, $type->type);
        }

        if ($type instanceof UnionType) {
            $parts = [];

            foreach ($type->types as $member) {
                if (! $this->isNullMember($member)) {
                    $parts[] = $this->slice($source, $member);
                }
            }

            return $parts === [] ? null : implode('|', $parts);
        }

        return null;
    }

    private function defaultsToNull(Param $param): bool
    {
        return $param->default instanceof ConstFetch
            && strtolower($param->default->name->toString()) === 'null';
    }

    private function isNullMember(Node $member): bool
    {
        return $member instanceof Identifier && strtolower($member->toString()) === 'null';
    }

    private function slice(string $source, Node $node): string
    {
        return substr($source, $node->getStartFilePos(), $node->getEndFilePos() + 1 - $node->getStartFilePos());
    }
}
