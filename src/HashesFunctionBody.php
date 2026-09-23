<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The fingerprints of the body a matched node runs as a function — what a clone rule compares — for a
 * match whose `$node` answers `functionBody()`, in whichever language its {@see SyntaxHash} reads.
 */
trait HashesFunctionBody
{
    /**
     * @return class-string<SyntaxHash>
     */
    abstract protected static function syntaxHash(): string;

    /**
     * A formatting-blind fingerprint of the statements this node runs as a function, with the name it
     * runs them under left out — two names for one body are the same code. Empty for a node that is
     * not a function with a body.
     */
    public function bodyHash(): string
    {
        $hash = static::syntaxHash();

        return $this->node->functionBody()->mapOr('', $hash::of(...));
    }

    /**
     * Like {@see bodyHash}, but blind to local names and string/number literals too — two bodies with one
     * control-flow skeleton that differ only in what they call their locals and which constants they use
     * (a type-2 clone).
     */
    public function shapeHash(): string
    {
        $hash = static::syntaxHash();

        return $this->node->functionBody()->mapOr('', $hash::normalized(...));
    }

    /**
     * How many nodes and expressions make up the function body — a size floor for a clone rule, since
     * short bodies are alike by coincidence. Zero for a node that is not a function with a body.
     */
    public function bodyNodeCount(): int
    {
        $hash = static::syntaxHash();

        return $this->node->functionBody()->mapOr(0, $hash::weight(...));
    }
}
