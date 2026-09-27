<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use JsonSerializable;

/**
 * A type as the source writes it: a name, a keyword, or a union or intersection of types, and whether it admits null.
 */
final readonly class WrittenType implements JsonSerializable
{
    /**
     * @param  list<WrittenType>|null  $members
     */
    private function __construct(
        public string $kind,
        public ?string $name,
        public ?array $members,
        public Nullability $nullability,
    ) {}

    public static function named(string $name): self
    {
        return new self('named', $name, null, Nullability::None);
    }

    public static function keyword(string $keyword): self
    {
        return new self('keyword', $keyword, null, in_array(strtolower($keyword), ['null', 'mixed'], true) ? Nullability::Admitted : Nullability::None);
    }

    /**
     * @param  list<WrittenType>  $members
     */
    public static function combined(string $kind, array $members): self
    {
        $admitsNull = array_filter($members, static fn (WrittenType $member): bool => $member->nullability->admitsNull()) !== [];

        return new self($kind, null, $members, $admitsNull ? Nullability::Admitted : Nullability::None);
    }

    /**
     * The type written `?T`: the same type, admitting null.
     */
    public function markedNullable(): self
    {
        return new self($this->kind, $this->name, $this->members, Nullability::Marked);
    }

    /**
     * The type as the source spells it: a name fully qualified, a keyword as written, members joined, `?` in front.
     */
    public function text(): string
    {
        $spelled = match ($this->kind) {
            'named' => '\\' . $this->name,
            'keyword' => $this->name,
            'union' => implode('|', array_map(static fn (WrittenType $member): string => $member->text(), $this->members)),
            'intersection' => implode('&', array_map(static fn (WrittenType $member): string => $member->text(), $this->members)),
        };

        return $this->nullability === Nullability::Marked ? '?' . $spelled : $spelled;
    }

    /**
     * The type as the contract writes it; a `?T` names its nullability after the rest, as its marker is read last.
     */
    public function jsonSerialize(): array
    {
        $written = ['text' => $this->text(), 'kind' => $this->kind];
        if ($this->name !== null) {
            $written['name'] = $this->name;
        }
        if ($this->members !== null) {
            $written['members'] = $this->members;
        }
        if ($this->nullability === Nullability::Admitted) {
            $written['nullable'] = true;
        }
        $written['origin'] = 'written';
        if ($this->nullability === Nullability::Marked) {
            $written['nullable'] = true;
        }

        return $written;
    }
}
