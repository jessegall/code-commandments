<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\PhpTypes;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;

/**
 * The `jessegall/php-types` `Option` type's knowledge, as a node: the two ways an Option gets
 * worn as a null — declared `?Option`, or `unwrapOr(null)` — stated once, so a detector reads
 * `$n->declaresNullableOption()` and the `Option` name lives here, not on the language-level
 * {@see AstNode}. Reached by type-hinting it in a `where` closure.
 */
final class OptionNode extends NodeMatch
{
    /**
     * Does this declaration (param, property, or return) type something as a nullable `Option`
     * — `?Option` / `Option | null` — an Option wearing a null costume?
     */
    public function declaresNullableOption(): bool
    {
        $type = match (true) {
            $this->node instanceof Param => $this->node->type,
            $this->node instanceof Property => $this->node->type,
            $this->node instanceof ClassMethod, $this->node instanceof Function_ => $this->node->returnType,
            default => null,
        };

        $class = TypeName::nullableClass($type);

        return $class !== null && self::shortName($class) === 'Option';
    }

    /**
     * Is this `->unwrapOr(null)` — collapsing an Option straight back to a nullable?
     */
    public function isUnwrapOrNull(): bool
    {
        if (! $this->node instanceof MethodCall && ! $this->node instanceof NullsafeMethodCall) {
            return false;
        }

        if (! $this->node->name instanceof Identifier || $this->node->name->toString() !== 'unwrapOr') {
            return false;
        }

        $args = $this->arguments();

        return isset($args[0]) && new AstNode($args[0]->value)->isNull();
    }
}
