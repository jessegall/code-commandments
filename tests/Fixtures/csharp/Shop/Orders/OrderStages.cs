namespace Shop.Orders;

// An order's stage as the fulfilment screens move it along.
public enum Stage
{
    Open,
    Packed,
    Shipped,
}

public sealed record StagedOrder(string Id, Stage Stage, string Note)
{
    public StagedOrder Shipped() => this with { Stage = Stage.Shipped, Note = "on its way" };
}

public static class Fulfilment2
{
    public static StagedOrder Dispatch(StagedOrder order) => order with { Stage = Stage.Shipped, Note = "on its way" };

    public static StagedOrder Send(StagedOrder order) => order.Shipped();

    public static StagedOrder Annotate(StagedOrder order, string note) => order with { Note = note };
}
