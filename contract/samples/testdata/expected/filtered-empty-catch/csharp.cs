namespace Orders;

public class Retry
{
    private bool again;

    public void Run()
    {
        try
        {
            Step();
        }
        catch (InvalidOperationException) when (again)
        {
        }
    }

    private void Step()
    {
    }
}
