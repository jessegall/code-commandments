<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;

/**
 * One class field — from a promoted constructor parameter or declared property — with its
 * name, type, attributes, visibility, and promotion status. Framework decorators read the
 * attributes to decide policy; the field itself stays generic so both engines can reuse it.
 */
final class ClassField
{
    /**
     * @param  list<AttributeGroup>  $attributeGroups
     */
    public function __construct(
        public readonly string $name,
        public readonly ?Node $type,
        public readonly array $attributeGroups,
        public readonly bool $isPublic,
        public readonly bool $isPromoted,
        public readonly ?string $docComment,
    ) {}

    /**
     * Does this field carry any attribute whose SHORT name matches one given — `hasAttribute('Hidden')`
     * is true for both `#[Hidden]` and `#[\Spatie\LaravelData\Attributes\Hidden]`. Short-name matching
     * is deliberate: the attribute's meaning is its class, and callers state the intent (the FQCN's
     * home) once in their own constant.
     */
    public function hasAttribute(string ...$shortNames): bool
    {
        foreach ($this->attributeNames() as $name) {
            if (in_array(self::shortName($name), $shortNames, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The fully-qualified name of every attribute on this field, in source order.
     *
     * @return list<string>
     */
    public function attributeNames(): array
    {
        $names = [];

        foreach ($this->attributeGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $names[] = $attribute->name->toString();
            }
        }

        return $names;
    }

    private static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
