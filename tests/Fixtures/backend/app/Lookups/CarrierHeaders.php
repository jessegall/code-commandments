<?php

namespace Shop\Lookups;

use JesseGall\CodeCommandments\Testing\Sinful;

final class CarrierHeaders
{
    /**
     * @param  list<HeaderBag>  $bags
     * @return list<string>
     */
    #[Sinful(CheckedThenRead::class)]
    public function carriers(array $bags, string $name): array
    {
        $found = [];
        foreach ($bags as $bag) {
            if ($bag->contains($name)) {
                $found[] = 'carrier ' . $bag->value($name);
            }
        }

        return $found;
    }
}
