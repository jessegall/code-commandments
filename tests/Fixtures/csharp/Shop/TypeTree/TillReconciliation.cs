using Shop.ExternalTree;

namespace Shop.LocalTree;

public sealed class TillClosingReport
{
    private readonly StockSettingsTreeImportedService details;
    private readonly IDictionary<string, int> counted;

    public TillClosingReport(
        IDictionary<string, int> group,
        // @sin ConcreteCrossModuleDependency
        StockSettingsTreeImportedService details)
    {
        this.details = details;
        this.counted = new Dictionary<string, int>(group);
    }

    public string Close(TextWriter report)
    {
        var summary = this.details.Read();
        foreach (var line in this.counted)
        {
            report.WriteLine(line.Key + " / " + line.Value);
        }
        return summary;
    }
}

public class CounterDrawer
{
    protected readonly Queue<int> notes = new();

    public virtual decimal Total() => this.notes.Sum();

    public void Accept(int amount) => this.notes.Enqueue(amount);
    public bool TryNext(out int amount) => this.notes.TryDequeue(out amount);
}

// @sin MiddleMan
public sealed class DrawerCounter
{
    private readonly CounterDrawer drawer;

    public DrawerCounter(CounterDrawer drawer)
    {
        this.drawer = drawer;
    }

    public void Accept(int amount)
    {
        this.drawer.Accept(amount);
    }

    public decimal Total()
    {
        return this.drawer.Total();
    }
}

public sealed class FrozenDrawer : CounterDrawer
{
    // @sin RefusedBequest
    public override decimal Total()
    {
        throw new InvalidOperationException();
    }

    public int SkipNotes(ISet<int> intended)
    {
        var skipped = 0;
        while (this.notes.TryPeek(out var value) && intended.Contains(value))
        {
            this.notes.Dequeue();
            skipped++;
        }
        return skipped;
    }
}

// @sin HandRolledRegistry
public sealed class CountedReceipts
{
    private readonly Dictionary<long, DateOnly> received = new();

    public void Arrived(long reference, DateOnly date)
    {
        this.received[reference] = date;
    }

    public void Retire(long reference, TextWriter archive)
    {
        if (!this.received.ContainsKey(reference))
        {
            throw new KeyNotFoundException();
        }
        archive.WriteLine(this.received[reference]);
        this.received.Remove(reference);
    }
}

public sealed record DrawerPrint(string Header);
public sealed record PrintedReceipt(DrawerPrint Caption);
public sealed record CashRecord(PrintedReceipt Receipt);

public sealed class TillReconciliation
{
    private readonly int first;
    private readonly Dictionary<string, int> issues = new();

    public TillReconciliation(int first)
    {
        this.first = first;
    }

    public int Count(CashRecord record)
    {
        // @sin TrainWreck
        var header = record.Receipt.Caption.Header;
        var seen = this.issues.GetValueOrDefault(header, this.first);
        this.issues[header] = seen + 1;
        return seen;
    }

    // @sin UnusedPrivateMember
    private string Report(int width)
    {
        var parts = this.issues.Keys.Select(key => key.PadRight(width));
        return string.Join("; ", parts);
    }

    public string Format
    {
        // @sin UselessPropertyHook
        get
        {
            return new string('-', 24) + "\n" + "TILL TOTALS";
        }
    }
}

public enum DrawerStage { Ready, Counted, Reported }

public sealed class ApprovedDrawer
{
    public DrawerStage Stage { get; private set; }

    public void MarkReported() { this.Stage = DrawerStage.Reported; }
}

public static class TillClosing
{
    public static string Publish(ApprovedDrawer prepared, string office, TextWriter outbox, int? clerk)
    {
        if (clerk is null) return office;
        // @sin GuardBesideTransition
        if (prepared.Stage != DrawerStage.Counted)
        {
            throw new InvalidOperationException();
        }
        prepared.MarkReported();
        outbox.WriteLine("Approved by " + clerk.Value);
        return office.ToUpperInvariant();
    }
}
