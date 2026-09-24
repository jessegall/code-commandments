// @example MutableStaticState good
namespace Shop.Orders;

// Numbers each order placed at one till: the count is the instance's own, so two tills never share it.
// @fixed MutableStaticState
public sealed class TillCounter
{
    private int placed;

    public int Next() => ++placed;
}
