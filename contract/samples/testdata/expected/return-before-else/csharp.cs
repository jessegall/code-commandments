namespace Orders;

public static class Signs
{
    public static string Sign(int n)
    {
        if (n < 0)
        {
            return "negative";
        }
        else
        {
            return "positive";
        }
    }
}
