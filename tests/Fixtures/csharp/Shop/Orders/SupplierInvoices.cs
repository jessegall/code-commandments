namespace Shop.Orders;

public sealed record InvoiceSupplier(Guid Id, string Name, string Iban);

// @sin CoupledFields
public sealed class SupplierInvoice
{
    public required InvoiceSupplier Supplier { get; init; }

    public Guid SupplierId { get; init; }

    public decimal Amount { get; init; }

    public string PayTo() => $"{Supplier.Iban} ({Supplier.Name}): {Amount:0.00}";
}

// @righteous CoupledFields
public sealed class Remittance
{
    public required InvoiceSupplier Supplier { get; init; }

    public decimal Amount { get; init; }

    public string Reference => $"{Supplier.Id:N}-{Amount:0}";
}
