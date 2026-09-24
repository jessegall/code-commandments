namespace Shop.Stock;

/// <summary>
/// <para>A supplier the shop reorders from.</para>
/// <para>Suppliers are rated each quarter on how often their deliveries arrive complete.</para>
/// </summary>
// @sin BloatedDocblock
public interface ISupplier
{
    string Name { get; }
}
