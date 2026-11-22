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
    // @sin UselessPropertyHook
    public int Steps => 5;
}

/// <summary>Takes a basket through payment.</summary>
// @fixed BloatedDocblock
public sealed class PaymentStep
{
    // @sin UselessPropertyHook
    public int Attempts => 3;
}
