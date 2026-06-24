<?php
namespace Acme\Notify\Justified;

final class ShapeRenderer
{
    public function __construct(private SchemaShapes $shapes) {}

    public function render(string $slug): array
    {
        $shape = $this->shapes->shape($slug);
        if ($shape->isNone()) {
            return [];
        }
        return $shape->unwrap();
    }

    public function withDefault(string $slug): array
    {
        return $this->shapes->shape($slug)->unwrapOr(['type' => 'object']);
    }
}
