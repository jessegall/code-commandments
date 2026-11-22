namespace Sizes;

public static class Size
{
    public static string Of(int n)
    {
        // @contract kind/if
        // @contract role/if/condition -> small
        // @contract role/if/else -> second
        if (n < 10) // @label small comparison
        {
            return "small";
        }
        else if (n < 100) // @label second if
        {
            return "medium";
        }
        else
        {
            return "large";
        }
    }
}
