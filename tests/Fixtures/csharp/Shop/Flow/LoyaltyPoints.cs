namespace Shop.Flow;

// LoyaltyPoints spends and expires the points a member collects.
public sealed class LoyaltyPoints
{
    public int Points { get; private set; }

    public string Tier { get; private set; } = "bronze";

    public string Redeem(int cost)
    {
        string result;
        // @sin DuplicatedBranchTail
        if (Points < cost)
        {
            Console.WriteLine(Points);
            result = "spent";
        }
        else
        {
            Points -= cost;
            result = "spent";
        }

        return result;
    }

    public void Expire(bool lapsed)
    {
        if (!lapsed)
        {
            return;
        }
        Points = 0;
        return;
        // @sin UnreachableStatement
        Tier = "bronze";
    }

    // @fixed UnreachableStatement
    public void ExpireLapsed(bool lapsed)
    {
        if (!lapsed)
        {
            return;
        }
        Points = 0;
    }

    // @righteous UnreachableStatement
    public string Render()
    {
        return Describe(Tier);

        static string Describe(string tier) => tier.ToUpperInvariant();
    }
}
