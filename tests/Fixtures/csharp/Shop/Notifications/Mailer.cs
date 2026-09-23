using System.Text;
using Shop.Orders;

namespace Shop.Notifications;

// The FIX for the copied mails: ONE method writes an order mail, and what differed between the copies —
// the event and the sign-off — is what the caller passes.
public sealed class OrderMailer(string customer)
{
    // @fixed NearDuplicateMethod
    public string Compose(Order order, string happened, string signOff)
    {
        var body = new StringBuilder();
        body.AppendLine($"Hello {customer},");
        body.AppendLine($"Your order {order.Reference} {happened}.");

        foreach (var line in order.Lines)
        {
            body.AppendLine($"- {line.Quantity} x {line.Sku}");
        }

        body.AppendLine(signOff);

        return body.ToString();
    }

    // @fixed NearDuplicateMethod
    public string Shipped(Order order) => Compose(order, "has shipped", "Thank you for shopping with us.");

    // @fixed NearDuplicateMethod
    public string Delivered(Order order) => Compose(order, "was delivered", "Enjoy your purchase.");
}
