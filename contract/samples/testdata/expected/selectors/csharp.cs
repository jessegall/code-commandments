using System.Collections.Generic;
using System.IO;

namespace Orders;

public sealed class Account
{
    private Ledger? ledger;

    public int Settle(List<int> amounts)
    {
        var total = 0;
        // @contract kind/loop
        // @contract fact/loop/form = "for-each"
        foreach (var amount in amounts) // @label each loop
        {
            // @contract role/if/condition -> both
            if (amount > 0 && amount < 100) // @label both logical
            {
                total += amount; // @contract kind/assignment
                continue; // @contract link/continue/destination -> each
            }
            break; // @contract link/break/destination -> each
        }
        try
        {
            this.ledger?.Record(total);
        }
        catch (IOException e)
        {
            throw new InvalidOperationException("not recorded", e);
        }

        return total;
    }
}
