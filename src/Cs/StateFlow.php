<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * How a C# type's own fields and properties are used from inside it: which of them each member reads together,
 * and where each is assigned and read. A name counts when it is the type's own — bare, or through `this.` —
 * and not a local or parameter the member shadows it with; `other.Cents` is another object's member.
 */
final class StateFlow
{
    /**
     * @var list<string>
     */
    private array $state;

    public function __construct(private readonly Node $type)
    {
        $this->state = $type->stateNames();
    }

    /**
     * Each member that reads the type's own state — a method, an accessor, a computed property; constructors
     * aside, since they build the state — with the names it reads, sorted.
     *
     * @return array<string, list<string>>
     */
    public function readsByMember(): array
    {
        $reads = [];

        foreach ($this->type->children as $member) {
            if ($member->role !== 'member' || $member->is('ConstructorDeclaration', 'FieldDeclaration') || $member->name === null) {
                continue;
            }

            $read = array_unique(array_map(static fn (Node $reference): string => $reference->memberName(), $this->readsIn($member)));
            sort($read);

            if ($read !== []) {
                $reads[$member->name] = $read;
            }
        }

        return $reads;
    }

    /**
     * The type's fields and properties declared as able to hold `null`.
     *
     * @return list<string>
     */
    public function nullableFields(): array
    {
        $nullable = array_filter($this->type->children, static fn (Node $member): bool => $member->is('FieldDeclaration', 'PropertyDeclaration') && $member->declaresNullableState());

        return array_values(array_merge([], ...array_map(static fn (Node $member): array => $member->heldStateNames(), $nullable)));
    }

    /**
     * Every value $field is given — its initializer, and the right side of each assignment to it, in any member.
     *
     * @return list<Node>
     */
    public function assignedValues(string $field): array
    {
        $values = [];

        foreach ($this->type->children as $member) {
            $values = [...$values, ...$member->initialValuesOf($field)];

            foreach ($this->writesIn($member) as $write) {
                if ($write->is('SimpleAssignmentExpression') && $write->children[0]->memberName() === $field && ! $write->children[0]->isShadowedIn($member)) {
                    $values[] = $write->children[1];
                }
            }
        }

        return $values;
    }

    /**
     * Every place $field is read, in any member but a constructor.
     *
     * @return list<Node>
     */
    public function reads(string $field): array
    {
        $members = array_filter($this->type->children, static fn (Node $member): bool => $member->role === 'member' && ! $member->is('ConstructorDeclaration', 'FieldDeclaration'));
        $references = array_merge([], ...array_map(fn (Node $member) => $this->readsIn($member), $members));

        return array_values(array_filter($references, static fn (Node $reference): bool => $reference->memberName() === $field));
    }

    /**
     * The places $member reads the type's own state — every reference to it but the targets its writes assign.
     *
     * @return list<Node>
     */
    private function readsIn(Node $member): array
    {
        $own = array_diff($this->state, $member->ownNames());
        $targets = array_map(static fn (Node $write): Node => $write->children[0], $this->writesIn($member));
        $found = array_merge([], ...array_map(static fn (Node $expression): array => $expression->ownStateReferences($own), $member->outermostExpressions()));

        return array_values(array_filter($found, static fn (Node $reference): bool => ! in_array($reference, $targets, true)));
    }

    /**
     * Every write expression in $member.
     *
     * @return list<Node>
     */
    private function writesIn(Node $member): array
    {
        $flat = array_merge([], ...array_map(static fn (Node $expression): array => $expression->flatten(), $member->outermostExpressions()));

        return array_values(array_filter($flat, static fn (Node $expression): bool => $expression->isWrite()));
    }
}
