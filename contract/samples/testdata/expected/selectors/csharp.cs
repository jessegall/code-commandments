using System.IO;

namespace Orders;

public sealed class Account
{
    private Ledger? ledger;

    public int Settle(List<int> amounts)
    {
        var total = 0;
        foreach (var amount in amounts)
        {
            if (amount > 0 && amount < 100)
            {
                total += amount;
                continue;
            }
            break;
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
