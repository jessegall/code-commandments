<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * The "does this method build something itself" signal shared by the envy analyses ({@see FeatureEnvy},
 * {@see LookupEnvy}): a method that `new`s a class or makes a named static call is doing construction work,
 * which changes how its use of another object reads. One home for the check both analyses lean on.
 */
trait DetectsConstruction
{
    private function constructs(ClassMethod $method): bool
    {
        $finder = new NodeFinder;

        if ($finder->findFirstInstanceOf($method->stmts, New_::class) !== null) {
            return true;
        }

        foreach ($finder->findInstanceOf($method->stmts, StaticCall::class) as $call) {
            if ($call->class instanceof Name) {
                return true;
            }
        }

        return false;
    }
}
