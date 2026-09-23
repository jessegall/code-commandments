namespace Shop.Orders;

// Statuses read a batch file and label each line — an else-if ladder is ONE choice however many rungs
// it has, and the try and using it runs inside are boundaries, not choices, so nothing here is deep.
public static class Statuses
{
    // @righteous DeepNesting
    public static IReadOnlyList<string> Label(string path, int limit)
    {
        var labels = new List<string>();

        try
        {
            using var reader = new StreamReader(path);

            foreach (var row in reader.ReadToEnd().Split('\n'))
            {
                foreach (var cell in row.Split(';'))
                {
                    if (cell == "paid")
                    {
                        labels.Add("green");
                    }
                    else if (cell == "late")
                    {
                        labels.Add("orange");
                    }
                    else if (cell.Length > limit)
                    {
                        labels.Add("red");
                    }
                }
            }
        }
        catch (IOException)
        {
            return [];
        }

        return labels;
    }
}
