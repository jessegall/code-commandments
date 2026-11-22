namespace Shop;

public static class Sums
{
    public static int Doubled(
        int total // @label total binding
    ) => total * 2; // @contract promise/read-links-binding -> total
}
