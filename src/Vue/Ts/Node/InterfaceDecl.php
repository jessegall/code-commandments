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
    use AggregatesMemberReferences;
    use MembersAsFields;

    /**
     * @param  list<Member>  $members
     */
    public function __construct(
        public readonly string $name,
        public readonly array $members,
        public readonly string $header = '',
    ) {}

    public function children(): array
    {
        return $this->members;
    }

    public function render(): string
    {
        $body = implode("\n", array_map(static fn (Member $m): string => '    ' . $m->render() . ';', $this->members));

        return "interface {$this->name}{$this->header} {\n{$body}\n}";
    }
}
