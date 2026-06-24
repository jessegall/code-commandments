<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\FeatureEnvy;

/**
 * A second project-owned collaborator the assembler coordinates.
 */
class HandleValidator
{
    public function accepts(string $handle): bool
    {
        return $handle !== '';
    }
}

/**
 * #203: ORCHESTRATION, not envy. resolveTarget queries NodeDescriptor's outputs
 * (owner 1) but ALSO coordinates the injected HandleValidator (owner 2) to do its
 * own layer's job. Touching two distinct collaborators, it covets neither — moving
 * it onto either owner would couple that owner to the other. Must NOT be flagged.
 */
class OrchestratingAssembler
{
    public function __construct(
        private readonly HandleValidator $validator,
    ) {}

    public function resolveTarget(NodeDescriptor $descriptor, string $port): mixed
    {
        $match = Option::first($descriptor->outputs, fn (object $o): bool => $o->hasName($port));

        return $this->validator->accepts($port) ? $match : null;
    }
}
