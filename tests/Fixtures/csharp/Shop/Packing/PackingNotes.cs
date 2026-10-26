using System.Text;

namespace Shop.Packing;

// Three loops over the same slip, to hold the line between writing it out and working it out. The first hands
// each note to the buffer as text: what it knows is the layout of a packing note, which is the printer's own and
// cannot move onto a slip. The second multiplies a line's own figures in the hole, which is the sin, and the
// third asks the line for the same words, which is the answer and must go unflagged. Totes.cs publishes this
// rule's example; these are the twins that hold its edge.
public static class PackingNotes
{
    // @righteous FeatureEnvy
    public static void WriteNotes(StringBuilder sheet, PackingSlip slip)
    {
        sheet.AppendLine("## Notes");

        foreach (var note in slip.Notes)
        {
            sheet.AppendLine($"  - {note}");
        }
    }

    // @sin FeatureEnvy
    public static void WriteLines(StringBuilder sheet, PackingSlip slip)
    {
        sheet.AppendLine("## Lines");

        foreach (var line in slip.Lines)
        {
            sheet.AppendLine($"  - {line.Count} x {line.PriceCents * line.Count}");
        }
    }

    // @righteous FeatureEnvy
    public static void WriteLinesTold(StringBuilder sheet, PackingSlip slip)
    {
        sheet.AppendLine("## Lines");

        foreach (var line in slip.Lines)
        {
            sheet.AppendLine($"  - {line.Told()}");
        }
    }
}

public sealed record PackingLine(int Count, int PriceCents)
{
    public string Told() => $"{this.Count} x {this.Count * this.PriceCents}";
}

public sealed record PackingSlip(IReadOnlyList<string> Notes, IReadOnlyList<PackingLine> Lines);
