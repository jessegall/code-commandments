namespace Shop.Returns;

// Two requests of two types each hand the ledger the same three ids. They share no type the ledger could take
// instead, so there is no one object to pass whole.
public sealed record CancelReturnLine(Guid CustomerId, Guid OrderId, Guid LineId);

public sealed record ReviseReturnLine(Guid CustomerId, Guid OrderId, Guid LineId, int Quantity);

public sealed class ReturnLedger
{
    private readonly HashSet<(Guid, Guid, Guid)> lines = [];

    public bool HoldsLine(Guid customerId, Guid orderId, Guid lineId) => lines.Contains((customerId, orderId, lineId));
}

public sealed class ReturnLineChecks(ReturnLedger ledger)
{
    // @righteous DerivedArgument
    public bool MayCancel(CancelReturnLine request) => ledger.HoldsLine(request.CustomerId, request.OrderId, request.LineId);

    // @righteous DerivedArgument
    public bool MayRevise(ReviseReturnLine request) =>
        request.Quantity > 0 && ledger.HoldsLine(request.CustomerId, request.OrderId, request.LineId);
}
