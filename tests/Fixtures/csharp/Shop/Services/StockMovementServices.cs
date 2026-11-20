using System;
using System.Collections.Generic;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;

namespace Shop.Services;

public sealed class StockMovementProjection {}
public sealed class RetiredStockReader {}

public sealed class StockMovementReader
{
    private readonly IServiceProvider services;

    public StockMovementReader(IServiceProvider services)
    {
        this.services = services;
    }

    public void PublishProjection(Action<StockMovementProjection> send)
    {
        // @sin ContainerReach
        var loaded = this.services.GetRequiredService<StockMovementProjection>();
        send(loaded);
    }

    public void Replay(DbSet<StockMovementProjection> movements, int[] queue, Action<string> publish)
    {
        for (var next = queue.Length - 1; next >= 0; next--)
        {
            var sequence = queue[next];
            // @sin QueryInLoop
            var movement = movements.Find(sequence);
            if (movement.Length > 0) publish(movement);
        }
    }
}

public static class StockMovementServices
{
    public static void Register(IServiceCollection services, IEnumerable<string> importFiles, Action<string> notice)
    {
        foreach (var source in importFiles)
        {
            notice(source);
        }
        // @sin OrphanedBinding
        services.AddSingleton<RetiredStockReader>();
        // @righteous OrphanedBinding
        services.AddSingleton<StockMovementProjection>();
    }
}
