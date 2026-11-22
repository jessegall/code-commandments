using System;
using System.Collections.Generic;
using System.IO;
using System.Threading.Tasks;

namespace Shop;

public static class Flow
{
    public static IEnumerable<int> Numbers(int[]? items, object gate, string? name)
    {
        var first = items?[0] ?? 0;
        int total = (int)first + 1;
        var label = name as string ?? $"item {total}";
        var shown = total > 0 ? -total : +total;
        var no = !(total > 0);
        var length = name!.Length;
        ++total;

        lock (gate)
        {
            total = ~total;
        }

        if (gate is string text) { }
        if (gate is not Stream) { }
        if (gate is IDisposable) { }

        int parsed;
        int.TryParse(label, out parsed);
        Swap(ref total, in shown);

        using (var reader = new StringReader(label))
        {
            ;
        }

        yield return typeof(int).GetHashCode();
        goto done;
    done:
        yield break;
    }

    public static async Task Drain(IAsyncEnumerable<int> source)
    {
        await using (var stream = new MemoryStream())
        {
        }

        await foreach (var number in source)
        {
        }
    }

    private static void Swap(ref int a, in int b) { }
}
