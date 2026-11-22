using System;

namespace Shop;

public interface IShape
{
    double Area();
}

public sealed class Circle : IShape
{
    public double Area() => 1;
}

public static class Tools
{
    public static T Make<T>() where T : IShape, new() => new T();

    public static unsafe int Apply(delegate*<int, int> apply) => apply(1);

    public static string Of(bool flag) => flag switch
    {
        true => "yes",
        false => "no",
    };

    public static void Run(object gate)
    {
        try
        {
            var shape = Make<Circle>();
        }
        finally
        {
            Console.WriteLine("done");
        }

        if (gate is not string) { }
        if (gate is not Circle) { }
    }
}
