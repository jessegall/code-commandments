using System;
using System.Collections.Generic;
using Shop.ExternalTree;

namespace Shop.LocalTree;

// @sin LoneImplementerInterface
public interface IStockSettingsTreePort
{
    int SendStockSettingsTree(string sku, int[] shelves);
}

public sealed class StockSettingsTreeExporter : IStockSettingsTreePort
{
    public int SendStockSettingsTree(string sku, int[] shelves)
    {
        var total = 0;
        foreach (var shelf in shelves)
        {
            if (shelf <= sku.Length) total++;
        }
        return total;
    }
}

public sealed class StockSettingsTreeOwner
{
    private readonly StockSettingsTreeImportedService counted;
    private readonly HashSet<int> posted = new();

    public StockSettingsTreeOwner(
        int first,
        // @sin ConcreteCrossModuleDependency
        StockSettingsTreeImportedService counted,
        int last)
    {
        this.counted = counted;
        for (var shelf = first; shelf <= last; shelf++)
        {
            this.posted.Add(shelf);
        }
    }

    public int Publish(Action<int, string> label)
    {
        var stock = this.counted.Read();
        foreach (var shelf in this.posted)
        {
            label(shelf, stock);
        }
        return this.posted.Count;
    }
}

public class StockSettingsTreeSource
{
    protected readonly List<int> holds = new();

    public virtual string Read() { return string.Join(", ", this.holds); }
    public void Queue(int slot) { this.holds.Add(slot); }
    public int Bins() { return this.holds.Count; }
}

// @sin MiddleMan
public sealed class StockSettingsTreeForwarder(StockSettingsTreeSource held)
{
    private readonly StockSettingsTreeSource storage = held;

    public void Queue(int slot)
    {
        this.storage.Queue(slot);
    }

    public int Bins()
    {
        return this.storage.Bins();
    }
}

public sealed class StockSettingsTreeBroken : StockSettingsTreeSource
{
    // @sin RefusedBequest
    public override string Read() { throw new NotSupportedException(); }

    public void RecordDiscards(IEnumerable<int> rejected)
    {
        foreach (var bin in rejected)
        {
            this.holds.Remove(bin);
        }
    }
}

// @sin HandRolledRegistry
public sealed class StockSettingsTreeRegistry
{
    private readonly Dictionary<int, int?> shelfCapacity = new();

    public void Allocate(int shelf, int? capacity)
    {
        this.shelfCapacity[shelf] = capacity;
    }

    public int FreePlaces(int shelf, int held)
    {
        if (this.shelfCapacity[shelf] is null)
        {
            throw new KeyNotFoundException();
        }
        return Math.Max(0, this.shelfCapacity[shelf].Value - held);
    }
}

public sealed record StockSettingsTreeLabel(string Text);
public sealed record StockSettingsTreeBin(StockSettingsTreeLabel Label);
public sealed record StockSettingsTreeShelf(StockSettingsTreeBin Bin);

public sealed class StockSettingsTreeDisplay(string aisle)
{
    public void Load(IEnumerable<StockSettingsTreeShelf> shelves, Action<string> post)
    {
        foreach (var shelf in shelves)
        {
            // @sin TrainWreck
            post(shelf.Bin.Label.Text);
        }
    }

    // @sin UnusedPrivateMember
    private string Draft(string[] tags)
    {
        return aisle + "\n" + string.Join("; ", tags);
    }

    public int Weight
    {
        // @sin UselessPropertyHook
        get
        {
            var ounces = 150;
            return ounces * 28;
        }
    }
}

public enum StockSettingsTreeStage { Open, Closed }

public sealed class StockSettingsTreeBatch
{
    public StockSettingsTreeStage Stage { get; private set; }
    public void Seal() { this.Stage = StockSettingsTreeStage.Closed; }
}

public sealed class StockSettingsTreeSender
{
    public int Dispatch(IEnumerable<StockSettingsTreeBatch> queued)
    {
        var sealedCount = 0;
        foreach (var stock in queued)
        {
            // @sin GuardBesideTransition
            if (StockSettingsTreeStage.Open != stock.Stage)
            {
                throw new InvalidOperationException();
            }
            stock.Seal();
            sealedCount++;
        }
        return sealedCount;
    }
}
