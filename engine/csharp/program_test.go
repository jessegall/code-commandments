package csharp_test

import ()

const orders = `using System;

namespace Shop;

public sealed class Order { }

public sealed class Orders
{
    public Order? Find(int id) => null;

    public void Ship(int id)
    {
        var forced = Find(id)!;
        var thrown = Find(id) ?? throw new InvalidOperationException();
        var tested = Find(id);
        if (tested is null) throw new InvalidOperationException();
        var kept = Find(id);
    }
}
`
