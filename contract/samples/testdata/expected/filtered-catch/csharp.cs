namespace Orders;

public class Reader
{
    public void Read()
    {
        try
        {
            Load();
        }
        catch (IOException e) when (e.HResult == 32)
        {
        }
    }

    private void Load()
    {
    }
}
