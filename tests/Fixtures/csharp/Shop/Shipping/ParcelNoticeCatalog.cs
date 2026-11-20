using System;

namespace Shop.Shipping;

public abstract class ParcelNotice
{
    public abstract string Label { get; }
}

public sealed class WaitingParcelNotice : ParcelNotice
{
    public override string Label => "Waiting";
}

public sealed class DelayedParcelNotice : ParcelNotice
{
    public override string Label => "Delayed";
}

public sealed class ReturnedParcelNotice : ParcelNotice
{
    public override string Label => "Returned";
}

public sealed class ShippedParcelNotice : ParcelNotice
{
    public override string Label => "Shipped";
}

public sealed class DeliveredParcelNotice : ParcelNotice
{
    public override string Label => "Delivered";
}

public static class ParcelNoticeCatalog
{
    public static int Post(Action<string, IReadOnlyList<Type>> publish, string gate, int waiting)
    {
        if (waiting < 1) return 0;
        // @sin ListMirrorsFolder
        publish(gate, [typeof(WaitingParcelNotice), typeof(DelayedParcelNotice), typeof(ReturnedParcelNotice), typeof(ShippedParcelNotice), typeof(DeliveredParcelNotice)]);
        return waiting;
    }

    // @righteous ListMirrorsFolder
    public static Type[] HeldNotices(int delayed)
    {
        return delayed > 0 ? [typeof(WaitingParcelNotice), typeof(DelayedParcelNotice)] : [typeof(WaitingParcelNotice)];
    }
}
