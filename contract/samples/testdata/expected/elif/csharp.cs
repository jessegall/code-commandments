namespace Sizes;

public static class Size
{
    public static string Of(int n)
    {
        if (n < 10)
        {
            return "small";
        }
        else if (n < 100)
        {
            return "medium";
        }
        else
        {
            return "large";
        }
    }
}
