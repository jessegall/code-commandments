using System.IO;

namespace Shop.Shipping;

public sealed class LabelReprint(Action<string> print)
{
    public void Print(int copies)
    {
        // @sin RepeatedFolderPath
        string file = Path.Combine("warehouse", "saved-labels", "reprint.pdf");
        for (int copy = 0; copy < copies; copy++)
        {
            print(file);
        }
    }
}
