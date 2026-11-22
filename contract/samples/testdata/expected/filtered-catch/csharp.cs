namespace Orders; // @contract role/file/modules -> line(1,module)

public class Reader
{
    public void Read()
    {
        // @contract role/try/handlers -> [catchHandler]
        try
        {
            Load();
        }
        // @label catchHandler handler
        // @label ioType type-expression
        // @label eBinding binding
        // @label filterCmp comparison
        // @contract kind/handler
        // @contract fact/handler/catchForm = "typed"
        // @contract role/handler/caughtTypes -> [ioType]
        // @contract role/handler/binding -> eBinding
        // @contract role/handler/filter -> filterCmp
        // @contract role/handler/body -> catchBody
        catch (IOException e) when (e.HResult == 32)
        // @label catchBody block
        {
        }
    }

    private void Load()
    {
    }
}
