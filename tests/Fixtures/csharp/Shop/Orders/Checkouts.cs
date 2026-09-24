namespace Shop.Orders;

/// <summary>
/// Takes a basket through payment.
///
/// It also reserves the stock, books the courier, sends the confirmation email and records the sale in the
/// ledger, retrying each step that fails.
/// </summary>
// @sin BloatedDocblock
public sealed class Checkout
{
    public int Steps => 5;
}

/// <summary>Takes a basket through payment.</summary>
// @fixed BloatedDocblock
public sealed class PaymentStep
{
    public int Attempts => 3;
}
