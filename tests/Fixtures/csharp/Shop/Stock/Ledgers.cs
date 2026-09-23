namespace Shop.Stock;

// Counts and reservations are read from two ledgers by the same method, one per file name — the path is
// the only thing that differs, so the path is the missing parameter.
public sealed class Ledgers(string folder)
{
    // @sin NearDuplicateMethod
    public IReadOnlyDictionary<string, int> Counts()
    {
        var found = new Dictionary<string, int>();

        try
        {
            foreach (var row in File.ReadAllLines(Path.Combine(folder, "counts.csv")))
            {
                var cells = row.Split(';');
                found[cells[0]] = int.Parse(cells[1]);
            }
        }
        catch (FileNotFoundException)
        {
            return new Dictionary<string, int>();
        }

        return found;
    }

    // @sin NearDuplicateMethod
    public IReadOnlyDictionary<string, int> Reservations()
    {
        var held = new Dictionary<string, int>();

        try
        {
            foreach (var entry in File.ReadAllLines(Path.Combine(folder, "reservations.csv")))
            {
                var parts = entry.Split(';');
                held[parts[0]] = int.Parse(parts[1]);
            }
        }
        catch (FileNotFoundException)
        {
            return new Dictionary<string, int>();
        }

        return held;
    }
}
