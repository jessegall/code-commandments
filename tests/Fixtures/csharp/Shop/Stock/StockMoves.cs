namespace Shop.Stock;

public sealed record StockMove(string Sku, int Quantity, string FromBin, string ToBin);

public sealed class MoveLog
{
    private readonly List<string> lines = [];

    public void Record(StockMove move, string sku) => lines.Add($"{sku}: {move.Quantity} {move.FromBin}->{move.ToBin}");

    // @fixed DerivedArgument
    public void RecordMove(StockMove move) => lines.Add($"{move.Sku}: {move.Quantity} {move.FromBin}->{move.ToBin}");
}

public sealed class Mover(MoveLog log)
{
    // @sin DerivedArgument
    public void Move(StockMove move) => log.Record(move, move.Sku);

    public void Undo(StockMove move)
    {
        var back = move with { FromBin = move.ToBin, ToBin = move.FromBin };
        // @sin DerivedArgument
        log.Record(back, back.Sku);
    }
}
