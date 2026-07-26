<?php

namespace Shop\Http\Pages\RepeatedCall;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedNamedCall;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * Repeated-call site 2 — the same `copyWith(metadata: …->toArray())` shape, in a class that carries a
 * required-port roster and decides the flag from it.
 */
final class PortHydrator
{
    /**
     * @var list<string>
     */
    private array $requiredPorts;

    public function __construct(string ...$requiredPorts)
    {
        $this->requiredPorts = $requiredPorts;
    }

    #[Sinful(RepeatedNamedCall::class)]
    public function hydrate(UiNode $node, string $name): UiNode
    {
        $required = in_array($name, $this->requiredPorts, true);

        return $node->copyWith(metadata: PortMeta::from(['name' => $name, 'required' => $required])->toArray());
    }
}
