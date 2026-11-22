using System.Collections.Generic;

namespace Shop;

public sealed class Point
{
    public int X { get; set; }
}

public static class Values
{
    public static void Build(int[] more)
    {
        var numbers = new[] { 1, 2 };
        var list = new List<int> { 3, 4 };
        var map = new Dictionary<string, int> { { "a", 1 } };
        var point = new Point { X = 1 };
        var anonymous = new { Name = "x", Count = 2 };
        (int A, string B) pair = (1, "a");
        int[] merged = [.. more, 5];
    }
}
