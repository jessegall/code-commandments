namespace Orders;

public class Flags
{
    private const int Limit = 3;

    public override string ToString() => Limit.ToString();

    public static async Task<int?> Run(ref int count, out string name, params int[] values)
    {
        name = "";
        for (var i = 0; i < 3; i++)
        {
            count++;
        }
        Show(label: "a", values);
        return await Load();
    }
}
