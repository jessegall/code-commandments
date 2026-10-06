<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use JsonSerializable;

/**
 * A type an outside member is written or documented with, as the contract writes one: its text, its kind, the name
 * it gives, and the types a union or an intersection joins.
 */
final readonly class OutsideType implements JsonSerializable
{
    /**
     * The types PHP itself names, which no class declares.
     */
    public const array BUILTINS = ['int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable', 'mixed', 'void', 'null', 'never', 'false', 'true', 'resource'];

    /**
     * @param  list<OutsideType>  $members
     */
    public function __construct(
        public string $text,
        public string $kind,
        public string $origin,
        public ?string $name = null,
        public array $members = [],
        public bool $nullable = false,
    ) {}

    public function jsonSerialize(): array
    {
        $written = ['text' => $this->text, 'kind' => $this->kind];
        if ($this->name !== null) {
            $written['name'] = $this->name;
        }
        if ($this->members !== []) {
            $written['members'] = $this->members;
        }
        if ($this->nullable) {
            $written['nullable'] = true;
        }
        $written['origin'] = $this->origin;

        return $written;
    }
}
