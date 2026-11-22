using System.Collections.Generic;

namespace Shop;

public sealed class Point
{
    public int X { get; set; }
}

public static class Values
{
    // @label elementType type-expression 30 inner
    // @contract role/type-expression/element at 30 -> elementType
    public static void Build(int[] more)
    {
        var numbers = new[] { 1, 2 };
        var list = new List<int> { 3, 4 };
        var map = new Dictionary<string, int> { { "a", 1 } };
        // @label pointType type-expression 25
        // @label pointInit collection
        // @label xEntry entry
        // @label xKey reference
        // @label oneValue literal
        // @contract kind/construction
        // @contract role/construction/type -> pointType
        // @contract role/construction/initializer -> pointInit
        // @contract kind/collection
        // @contract fact/collection/form = "object"
        // @contract role/collection/entries -> [xEntry]
        // @contract kind/entry
        // @contract role/entry/key -> xKey
        // @contract role/entry/value -> oneValue
        var point = new Point { X = 1 };
        var anonymous = new { Name = "x", Count = 2 };
        // @label elemA tuple-element 10
        // @label elemB tuple-element 17
        // @label aType type-expression 10
        // @label tupleOne entry 35
        // @label tupleA entry 38
        // @label oneLit19 literal 35
        // @contract kind/tuple-type
        // @contract role/tuple-type/elements -> [elemA, elemB]
        // @contract kind/tuple-element at 10
        // @contract fact/tuple-element/label at 10 = "A"
        // @contract role/tuple-element/type at 10 -> aType
        // @contract fact/collection/form = "tuple"
        // @contract role/collection/entries -> [tupleOne, tupleA]
        // @contract role/entry/value at 35 -> oneLit19
        (int A, string B) pair = (1, "a");
        // @contract fact/entry/expansion at 25 = "positional"
        int[] merged = [.. more, 5];
    }
}
