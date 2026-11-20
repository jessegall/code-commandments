using Microsoft.EntityFrameworkCore;
using Microsoft.Extensions.DependencyInjection;

namespace Shop.Services;

public sealed class TillWorkbook {}
public sealed class ArchivedDrawerFormat {}

public sealed class TillEntries
{
    private readonly IServiceProvider books;
    private readonly string office;

    public TillEntries(IServiceProvider books, string office)
    {
        this.books = books;
        this.office = office;
    }

    public IList<object> Open(int drawer, byte[] ticket)
    {
        var lines = new List<object>();
        lines.Add(this.office);
        lines.Add(drawer);
        if (ticket.Length > 0)
        {
            // @sin ContainerReach
            lines.Add(this.books.GetRequiredService<TillWorkbook>());
        }
        return lines;
    }

    public string Pack(IReadOnlyList<int> drawers, DbSet<TillWorkbook> records, byte separator)
    {
        var packet = new System.Text.StringBuilder();
        for (var index = 0; index < drawers.Count; index++)
        {
            if (drawers[index] < 1) continue;
            // @sin QueryInLoop
            var label = records.Find(drawers[index]);
            packet.Append(label.Trim()).Append((char)separator);
        }
        return packet.ToString();
    }

    public static bool RegisterNamed(IServiceCollection into, TextWriter announce, string office)
    {
        if (office == "") return false;
        // @sin OrphanedBinding
        into.AddSingleton<ArchivedDrawerFormat>();
        into.AddSingleton<TillWorkbook>();
        announce.WriteLine(office.ToUpperInvariant());
        return true;
    }
}
