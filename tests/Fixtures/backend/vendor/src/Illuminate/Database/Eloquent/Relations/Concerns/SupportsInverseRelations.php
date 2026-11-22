<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait SupportsInverseRelations
{
    protected ?string $inverseRelationship = NULL;
    /** @return $this */
    public function inverse(?string $relation = NULL) {}
    /** @return $this */
    public function chaperone(?string $relation = NULL) {}
    protected function guessInverseRelation(): ?string {}
    protected function getPossibleInverseRelations(): array {}
    /** @return \Illuminate\Database\Eloquent\Collection */
    protected function applyInverseRelationToCollection($models, ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    /** @return \Illuminate\Database\Eloquent\Model */
    protected function applyInverseRelationToModel(\Illuminate\Database\Eloquent\Model $model, ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    /** @return string|null */
    public function getInverseRelationship() {}
    /** @return $this */
    public function withoutInverse() {}
    /** @return $this */
    public function withoutChaperone() {}
}
