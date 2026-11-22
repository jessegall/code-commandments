using System.Collections.Generic;

namespace Orders;

public class Visitor
{
    public void Visit(List<int> values)
    {
        // @contract kind/function
        values.ForEach(value =>
        {
            // @contract kind/return
            // @contract role/return/values -> []
            return;
        });
    }
}
