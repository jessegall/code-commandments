<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;

/**
 * The declared signature a CALL SITE fills — the class that declares the method, and the method's name.
 *
 * Resolved to the DECLARING class, so calls made through different subclasses of one base name the same
 * signature; a `new` fills a constructor and a static call names its own class. The one place a rule
 * about arguments asks "what did this call actually reach", so no two of them answer it differently.
 */
final class Callee
{
    private function __construct(
        public readonly string $class,
        public readonly string $method,
    ) {}

    /**
     * The signature $call fills, or null when the callee is not in the scanned tree — an unknown
     * signature proves nothing about what its parameters wanted.
     */
    public static function of(NodeMatch $call, TypeResolver $resolver): ?self
    {
        [$receiver, $method] = match (true) {
            $call->node instanceof New_ => [$call->newClassName(), '__construct'],
            $call->node instanceof StaticCall => [$call->staticCallClass(), $call->staticCallMethod()],
            default => [ReceiverResolver::typeOf($call), $call->methodCallName()],
        };

        $owner = $method === null ? null : $resolver->declaringClassOfMethod($receiver, $method);

        return $owner === null ? null : new self($owner, $method);
    }

    /**
     * A stable key for one PARAMETER of this signature — what a cross-call-site rule buckets by.
     */
    public function slot(int $position): string
    {
        return $this->class . '::' . $this->method . '#' . $position;
    }

    /**
     * Does parameter $position take a plain SCALAR? A parameter already typed as an object is asking for
     * the object, and an unknown signature proves nothing either way.
     */
    public function takesAScalarAt(int $position, TypeResolver $resolver): bool
    {
        $declared = $resolver->paramTypeOf($this->class, $this->method, $position);

        return $declared !== null && ! TypeName::isClassName($declared);
    }
}
