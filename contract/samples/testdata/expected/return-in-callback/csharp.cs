namespace Orders;

public class Visitor
{
    public void Visit(List<int> values)
    {
        values.ForEach(value =>
        {
            return;
        });
    }
}
