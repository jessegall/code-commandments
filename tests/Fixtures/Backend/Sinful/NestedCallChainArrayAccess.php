<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful;

/**
 * Shows several classes that nest through each other, with the string
 * indexing happening several calls deep. The prophet should flag each
 * offending access and the "source" hint should point at the method or
 * property that ought to be typed.
 */
class OrderController
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    public function show(array $request): string
    {
        $id = $request['id'];
        $order = $this->orders->describe($id);

        return $order['title'];
    }
}

class OrderService
{
    public function __construct(
        private readonly OrderRepository $repo,
    ) {}

    public function describe(string $id): array
    {
        $row = $this->repo->find($id);

        return [
            'title' => $row['title'] . ' (#' . $row['nodeId'] . ')',
        ];
    }
}

class OrderRepository
{
    /** @var array<string, array<string, string>> */
    private array $rows = [];

    public function find(string $id): array
    {
        return $this->rows[$id];
    }
}
