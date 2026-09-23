namespace Shop.Orders;

public enum OrderStatus { Placed, Paid, Shipped, Delivered, Cancelled }

// A badge colour is picked by testing the status against one member after another — a dispatch
// written as a ladder, so a new status is a new rung someone has to remember to add.
public static class StatusBadge
{
    public static string Colour(OrderStatus status)
    {
        // @sin SubjectLadder
        if (status == OrderStatus.Placed)
        {
            return "grey";
        }
        else if (status == OrderStatus.Paid)
        {
            return "blue";
        }
        else if (status == OrderStatus.Shipped)
        {
            return "orange";
        }
        else if (status == OrderStatus.Delivered)
        {
            return "green";
        }

        return "red";
    }

    // @fixed SubjectLadder
    public static string ColourOf(OrderStatus status) => status switch
    {
        OrderStatus.Placed => "grey",
        OrderStatus.Paid => "blue",
        OrderStatus.Shipped => "orange",
        OrderStatus.Delivered => "green",
        OrderStatus.Cancelled => "red",
        _ => throw new ArgumentOutOfRangeException(nameof(status)),
    };
}
