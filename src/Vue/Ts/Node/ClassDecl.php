<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A `class Name extends Base implements I { … }` declaration — its {@see members} as
 * {@see MethodDecl}s and {@see FieldDecl}s, with type parameters and the heritage clause kept
 * verbatim on the {@see header}. Its members are its {@see children}, which is what lets a selector
 * find a method — and a method's parameters — without knowing it sits inside a class.
 */
final class ClassDecl extends Node
{
    /**
     * @param  list<MethodDecl|FieldDecl>  $members
     */
    public function __construct(
        public readonly string $name,
        public readonly array $members,
        public readonly string $header = '',
        public readonly bool $abstract = false,
    ) {}

    public function declaredNames(): array
    {
        return [$this->name];
    }

    public function children(): array
    {
        return $this->members;
    }

    /**
     * The member declared under $name — a method or a field, whichever it is. Null when the class
     * declares none, so a caller asks once rather than searching both lists.
     */
    public function member(string $name): MethodDecl|FieldDecl|null
    {
        foreach ($this->members as $member) {
            if ($member->name === $name) {
                return $member;
            }
        }

        return null;
    }

    /**
     * @return list<MethodDecl>
     */
    public function methods(): array
    {
        return array_values(array_filter($this->members, static fn (Node $m): bool => $m instanceof MethodDecl));
    }

    /**
     * @return list<FieldDecl>
     */
    public function fields(): array
    {
        return array_values(array_filter($this->members, static fn (Node $m): bool => $m instanceof FieldDecl));
    }

    public function render(): string
    {
        $body = implode("\n", array_map(static fn (Node $m): string => '    ' . $m->render(), $this->members));
        $abstract = $this->abstract ? 'abstract ' : '';

        return "{$abstract}class {$this->name}{$this->header} {\n{$body}\n}";
    }
}
