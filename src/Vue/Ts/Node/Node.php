<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * The root of the `<script setup>` syntax tree — every declaration, pattern, and type is a Node.
 * Each renders back to valid TypeScript ({@see render}), so a type the parser understood can be
 * re-emitted into a generated component exactly, and a {@see TypeNode} additionally reports the
 * type names it {@see TypeNode::references} (for carrying a local type into an extracted child).
 */
abstract class Node
{
    abstract public function render(): string;
}
