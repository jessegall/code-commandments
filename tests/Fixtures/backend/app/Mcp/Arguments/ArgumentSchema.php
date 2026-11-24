<?php

namespace Shop\Mcp\Arguments;

/**
 * Builds the declaration of one argument a tool takes.
 */
final class ArgumentSchema
{
    public function text(): ArgumentRule
    {
        return new ArgumentRule('string');
    }

    public function number(): ArgumentRule
    {
        return new ArgumentRule('integer');
    }
}
