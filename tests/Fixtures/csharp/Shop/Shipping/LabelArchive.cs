using System.IO;

namespace Shop.Shipping;

public sealed class LabelArchive(Action<string, byte[]> save)
{
    private static readonly string SavedLabels = Path.Combine("warehouse", "saved-labels");

    public void Write(byte[] printed)
    {
        // @sin RepeatedFolderPath
        save(Path.Combine("warehouse", "saved-labels", "archive.pdf"), printed);
    }

    // @fixed RepeatedFolderPath
    public void WriteToArchive(byte[] printed)
    {
        save(Path.Combine(SavedLabels, "archive.pdf"), printed);
    }
}
