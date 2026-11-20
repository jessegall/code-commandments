using System;
using System.Collections.Generic;
using Shop.ExternalTree;

namespace Shop.LocalTree;

// @sin LoneImplementerInterface
public interface IVoucherSettingsTreePort
{
    bool ClaimVoucher(Guid serial);
    void Retire(Guid serial);
    int Outstanding();
}

public sealed class VoucherSettingsTreeExporter : IVoucherSettingsTreePort
{
    private readonly HashSet<Guid> claimed = new();
    public bool ClaimVoucher(Guid serial) { return this.claimed.Add(serial); }
    public void Retire(Guid serial) { this.claimed.Remove(serial); }
    public int Outstanding() { return this.claimed.Count; }
}

public sealed class VoucherSettingsTreeOwner
{
    private readonly VoucherSettingsTreeImportedService issuer;
    private readonly decimal limit;

    public VoucherSettingsTreeOwner(
        // @sin ConcreteCrossModuleDependency
        VoucherSettingsTreeImportedService issuer,
        decimal threshold)
    {
        if (threshold < 0m) throw new ArgumentOutOfRangeException();
        this.issuer = issuer;
        this.limit = threshold;
    }

    public string? Quote(decimal sold)
    {
        return sold <= this.limit ? this.issuer.Read() : null;
    }
}

public class VoucherSettingsTreeSource
{
    protected readonly Dictionary<Guid, decimal> scheduled = new();

    public virtual string Read() { return this.scheduled.Count.ToString(); }

    public void Credit(Guid serial, decimal amount)
    {
        this.scheduled[serial] = amount;
    }

    public bool HasCredit(Guid serial)
    {
        return this.scheduled.ContainsKey(serial);
    }

    public decimal Amount(Guid serial) { return this.scheduled[serial]; }
}

// @sin MiddleMan
public sealed class VoucherSettingsTreeForwarder(VoucherSettingsTreeSource reserves)
{
    private readonly VoucherSettingsTreeSource floatCredit = reserves;

    public void Credit(Guid code, decimal cents) { this.floatCredit.Credit(code, cents); }
    public bool HasCredit(Guid code) { return this.floatCredit.HasCredit(code); }
    public decimal Amount(Guid code) { return this.floatCredit.Amount(code); }
}

public sealed class VoucherSettingsTreeBroken : VoucherSettingsTreeSource
{
    // @sin RefusedBequest
    public override string Read() { throw new NotSupportedException(); }

    public decimal Forgive(Guid serial)
    {
        if (!this.scheduled.Remove(serial, out var forgiven)) return 0m;
        return forgiven;
    }
}

// @sin HandRolledRegistry
public sealed class VoucherSettingsTreeRegistry
{
    private readonly Dictionary<Guid, decimal> credit = new();

    public void Issue(Guid serial, decimal amount)
    {
        this.credit[serial] = amount;
    }

    public void Redeem(Guid serial, Action<decimal> post)
    {
        if (this.credit[serial] <= 0m)
        {
            throw new KeyNotFoundException();
        }
        post(this.credit[serial]);
        this.credit.Remove(serial);
    }
}

public sealed record VoucherSettingsTreeLabel(string Text);
public sealed record VoucherSettingsTreeBin(VoucherSettingsTreeLabel Label);
public sealed record VoucherSettingsTreeShelf(VoucherSettingsTreeBin Bin);

public sealed class VoucherSettingsTreeDisplay(DateOnly date, int batch)
{
    public int Watch(VoucherSettingsTreeShelf home, IReadOnlyDictionary<string, int> registered)
    {
        // @sin TrainWreck
        var packed = home.Bin.Label.Text;
        return registered.GetValueOrDefault(packed);
    }

    // @sin UnusedPrivateMember
    private string Draft(decimal gross)
    {
        return $"{date:yyyyMMdd}/{batch:D6}: {gross:0.00}";
    }

    public string Prefix
    {
        // @sin UselessPropertyHook
        get
        {
            return DateTime.UtcNow.Year.ToString("D4") + "-V-";
        }
    }
}

public enum VoucherSettingsTreeStage { Open, Closed }

public sealed class VoucherSettingsTreeBatch
{
    public VoucherSettingsTreeStage Stage { get; private set; }
    public void Seal() { this.Stage = VoucherSettingsTreeStage.Closed; }
}

public sealed class VoucherSettingsTreeSender
{
    public bool Dispatch(int number, VoucherSettingsTreeBatch voucher, Action<int> post)
    {
        if (number < 1) return false;
        // @sin GuardBesideTransition
        if (voucher.Stage == VoucherSettingsTreeStage.Closed)
        {
            throw new ArgumentException();
        }
        voucher.Seal();
        post(number);
        return true;
    }
}
