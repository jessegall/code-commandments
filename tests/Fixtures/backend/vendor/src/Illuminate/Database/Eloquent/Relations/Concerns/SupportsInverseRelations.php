<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

trait SupportsInverseRelations
{
    protected ?string $inverseRelationship = NULL;
    public function inverse(?string $relation = NULL) {}
    public function chaperone(?string $relation = NULL) {}
    protected function guessInverseRelation(): ?string {}
    protected function getPossibleInverseRelations(): array {}
    protected function applyInverseRelationToCollection($models, ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    protected function applyInverseRelationToModel(\Illuminate\Database\Eloquent\Model $model, ?\Illuminate\Database\Eloquent\Model $parent = NULL) {}
    public function getInverseRelationship() {}
    public function withoutInverse() {}
    public function withoutChaperone() {}
}
