using System;
using System.Collections.Generic;
using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;

namespace Shop.Services;

public sealed class ParcelSnapshotProjection {}
public sealed class OldParcelIndex {}

public sealed class ParcelSnapshotReader(IServiceProvider source, Action<string> intake)
{
    private readonly IServiceProvider intakeServices = source;

    public bool PublishProjection(string gate)
    {
        if (gate.Length == 0) return false;
        // @sin ContainerReach
        var projection = this.intakeServices.GetRequiredService<ParcelSnapshotProjection>();
        intake(gate + ": " + projection.GetType().Name);
        return true;
    }

    public decimal ReadParcels(DbSet<ParcelSnapshotProjection> parcels, Queue<int> queue, decimal running)
    {
        var total = running;
        while (queue.TryDequeue(out var next))
        {
            // @sin QueryInLoop
            var label = parcels.Find(next);
            total += label.Length;
            intake(label);
        }
        return total;
    }

    // @righteous QueryInLoop
    public string ReadOne(DbSet<ParcelSnapshotProjection> rows, int identifier)
    {
        return rows.Find(identifier);
    }

    // @righteous ContainerReach
    public string ProjectionName(ParcelSnapshotProjection projection)
    {
        return projection.GetType().Name;
    }
}

public static class ParcelSnapshotServices
{
    public static IServiceCollection Register(IServiceCollection catalogue, int oldest, Action<int> audit)
    {
        // @sin OrphanedBinding
        catalogue.AddSingleton<OldParcelIndex>();
        audit(oldest);
        // @righteous OrphanedBinding
        catalogue.AddSingleton<ParcelSnapshotProjection>();
        return catalogue;
    }
}
