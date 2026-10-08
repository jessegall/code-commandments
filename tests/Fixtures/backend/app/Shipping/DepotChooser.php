<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Testing\Righteous;

final class DepotChooser
{
    /**
     * @param list<DepotRule> $rules
     * @param array<string, Depot> $defaults
     */
    public function __construct(
        private readonly array $rules,
        private readonly array $defaults,
    ) {}

    #[Righteous(NullableRegistryLookup::class)]
    public function chooseFor(Consignment $consignment, string $region): ?Depot
    {
        foreach ($this->rules as $rule) {
            if ($rule->admits($consignment)) {
                return $rule->depot;
            }
        }

        return $this->defaults[$region] ?? null;
    }
}
