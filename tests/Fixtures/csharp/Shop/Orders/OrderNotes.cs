namespace Shop.Orders;

// The notes a customer left on an order, printed on the packing slip.
public sealed class OrderNotes
{
    public IEnumerable<string>? Notes { get; init; }

    private readonly IEnumerable<string> standard = ["Thank you for shopping with us."];

    public int Count()
    {
        var count = 0;

        // @sin CoalescedLoopSubject
        foreach (var note in Notes ?? Enumerable.Empty<string>())
        {
            count += note.Length > 0 ? 1 : 0;
        }

        return count;
    }

    public string Printed()
    {
        var printed = "";

        // @righteous CoalescedLoopSubject
        foreach (var note in Notes ?? standard)
        {
            printed += note + "\n";
        }

        return printed;
    }
}
