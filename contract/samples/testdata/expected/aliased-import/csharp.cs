// @contract kind/import
// @contract role/import-binding/alias -> alias
using Money = System.Decimal; // @label alias binding

namespace Orders;

public static class Totals
{
    public static int Of(Money amount) => 0;
}
