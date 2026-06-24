<?php

namespace App\Access;

/**
 * Decides allow/deny by asking each access rule in turn — any grant wins.
 */
final class AccessPolicyResolver
{
    /**
     * @var list<AccessRule>
     */
    private array $rules;

    public function __construct(AccessRule ...$rules)
    {
        $this->rules = array_values($rules);
    }

    public function allows(AccessRequest $request): bool
    {
        foreach ($this->rules as $rule) {
            if ($rule->allows($request)) {
                return true;
            }
        }

        return false;
    }
}
