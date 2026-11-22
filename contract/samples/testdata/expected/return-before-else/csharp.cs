namespace Orders;

public static class Signs
{
    public static string Sign(int n)
    {
        // @contract kind/if
        // @contract role/if/else -> fallback
        if (n < 0)
        {
            // @contract role/return/values -> [negative]
            return "negative"; // @label negative literal
        }
        else
        { // @label fallback block
            return "positive";
        }
    }
}
