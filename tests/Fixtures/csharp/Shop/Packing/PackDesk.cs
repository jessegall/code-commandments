namespace Shop.Packing;

// The desk that packs an order: it writes the slip and nothing else. The courier it also asks for is kept in a
// field of its own and read nowhere, so every registration of this type resolves one for nothing.
public sealed class PackDesk
{
    private readonly ISlipPrinter printer;

    // @sin UnreadCollaborator
    private readonly ICourier courier;

    public PackDesk(ISlipPrinter printer, ICourier courier)
    {
        this.printer = printer;
        this.courier = courier;
    }

    public int Pack(string order, IReadOnlyList<string> lines)
    {
        this.printer.Print($"{order}: {lines.Count} lines");

        return lines.Count;
    }
}

public interface ISlipPrinter
{
    void Print(string text);
}

public interface ICourier
{
    void Book(string order);
}
