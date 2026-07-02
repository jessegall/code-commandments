<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * An `interface Name { … }` declaration — its members as a `name => type` map ({@see fields}), and a
 * faithful {@see render} so the extract scribe can carry a parent-local interface into an extracted
 * child verbatim. Type parameters and `extends` clauses are preserved as raw text on the header.
 */
final class InterfaceDecl extends Node
{
    /**
     * @param  list<Member>  $members
     */
    public function __construct(
        public readonly string $name,
        public readonly array $members,
        public readonly string $header = '',
    ) {}

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->members as $member) {
            $fields[$member->name] = $member->type()->render();
        }

        return $fields;
    }

    public function render(): string
    {
        $body = implode("\n", array_map(static fn (Member $m): string => '    ' . $m->render() . ';', $this->members));

        return "interface {$this->name}{$this->header} {\n{$body}\n}";
    }

    /**
     * The named types this interface's members reference — so carrying it into an extracted child
     * can carry its dependencies too.
     *
     * @return list<string>
     */
    public function references(): array
    {
        $names = [];

        foreach ($this->members as $member) {
            $names = [...$names, ...$member->references()];
        }

        return $names;
    }
}
