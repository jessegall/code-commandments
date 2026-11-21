namespace Shop.Picking;

public sealed class PickRetries
{
    public void Shielded()
    {
        try
        {
            this.Pick();
        }
        // @righteous RethrownUnchanged
        catch (System.OperationCanceledException)
        {
            throw;
        }
        catch (System.Exception)
        {
            this.Pick();
        }
    }

    public void LastOfSeveral()
    {
        try
        {
            this.Pick();
        }
        catch (System.OperationCanceledException)
        {
            this.Pick();
        }
        // @sin RethrownUnchanged
        catch (System.Exception)
        {
            throw;
        }
    }

    private void Pick()
    {
    }
}
