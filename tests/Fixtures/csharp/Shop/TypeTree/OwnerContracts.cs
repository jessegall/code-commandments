namespace Shop.LocalTree;

// @righteous LoneImplementerInterface
public interface IReceiptFormatter
{
    string FormatNumber(string reference);
}

public class PlainReceipt : IReceiptFormatter
{
    public virtual string FormatNumber(string reference)
    {
        return reference.Trim();
    }
}

public sealed class NumberedReceipt : IReceiptFormatter
{
    public string FormatNumber(string reference)
    {
        return "Receipt #" + reference;
    }
}

public sealed class ReceiptJob
{
    private readonly IReceiptFormatter formatter;
    private readonly string reference;

    public ReceiptJob(
        // @righteous ConcreteCrossModuleDependency
        // @fixed ConcreteCrossModuleDependency
        IReceiptFormatter formatter,
        string reference)
    {
        this.formatter = formatter;
        this.reference = reference;
    }

    // @righteous MiddleMan
    // @fixed MiddleMan
    public string Caption()
    {
        return "Customer copy: " + this.FormatCurrent();
    }

    // @righteous UnusedPrivateMember
    private string FormatCurrent()
    {
        return this.formatter.FormatNumber(this.reference);
    }

    public string Key
    {
        // @righteous UselessPropertyHook
        // @fixed UselessPropertyHook
        get { return this.reference; }
    }
}

public sealed class LongReceipt : PlainReceipt
{
    // @righteous RefusedBequest
    // @fixed RefusedBequest
    public override string FormatNumber(string reference)
    {
        return "Customer copy: " + base.FormatNumber(reference);
    }
}

// @righteous HandRolledRegistry
public sealed class ReceiptHistory
{
    private readonly System.Collections.Generic.Dictionary<string, string> tags = new();

    public bool HasTag(string key)
    {
        return this.tags.ContainsKey(key);
    }
}

public sealed class ReceiptIdentity
{
    // @righteous TrainWreck
    // @fixed TrainWreck
    public string ReceiptCaption(ReceiptJob job)
    {
        return job.Caption();
    }
}

public sealed class ReceiptDispatch
{
    private bool queued = true;

    // @righteous GuardBesideTransition
    // @fixed GuardBesideTransition
    public void Dispatch()
    {
        if (!this.queued)
        {
            throw new System.InvalidOperationException();
        }
        this.queued = false;
    }
}
