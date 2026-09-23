using System.Text;
using Shop.Orders;

namespace Shop.Notifications;

public sealed class DeliveredMail(string customer)
{
    public string Subject => "Your order has arrived";

    // @sin NearDuplicateMethod
    public string Compose(Order order)
    {
        var mail = new StringBuilder();
        mail.AppendLine($"Hello {customer},");
        mail.AppendLine($"Your order {order.Reference} was delivered.");

        foreach (var item in order.Lines)
        {
            mail.AppendLine($"- {item.Quantity} x {item.Sku}");
        }

        mail.AppendLine("Enjoy your purchase.");

        return mail.ToString();
    }
}
