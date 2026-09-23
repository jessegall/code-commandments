namespace Shop.Documents;

// Old invoices are kept as numbered files; a number that does not parse is a broken archive.
public static class InvoiceArchive
{
    public static int NumberOf(string fileName)
    {
        try
        {
            return int.Parse(Path.GetFileNameWithoutExtension(fileName));
        }
        catch (FormatException)
        {
            // @sin WrappingWithoutCause
            throw new ArgumentException($"{fileName} is not an invoice number.", nameof(fileName));
        }
    }

    public static string Contents(string fileName)
    {
        try
        {
            return File.ReadAllText(fileName);
        }
        catch (IOException)
        {
            // @righteous WrappingWithoutCause
            throw;
        }
    }
}
