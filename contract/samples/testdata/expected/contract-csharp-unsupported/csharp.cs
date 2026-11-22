namespace Shop;

public delegate int Op(int a);

public static class Maths
{
    public static int Add(int a)
    {
        return checked(a + 1);
    }
}
