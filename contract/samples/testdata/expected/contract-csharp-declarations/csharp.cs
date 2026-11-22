using System;

namespace Shop;

public class Base { }

public interface IShape
{
    double Area();
}

[Serializable]
public class Box<T> : Base, IShape
{
    [NonSerialized]
    private int cached;

    [Obsolete("old")]
    public const int Limit = 3;

    public int Count
    {
        get { return cached; }
        set { cached = value; }
    }

    [Obsolete("use Size")]
    public double Area() => 0;

    public T Make() => default(T);

    public int Zero() => default;

    public TOut Convert<TOut>([Obsolete] T item, int scale = 1) => default!;
}

public enum Level
{
    [Obsolete] Low = 1,
    High = 2,
}
