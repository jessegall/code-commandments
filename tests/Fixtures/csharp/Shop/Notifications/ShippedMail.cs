using System.Text;
using Shop.Orders;

namespace Shop.Notifications;

// The shipped mail and the delivered mail are one mail with a different subject and sign-off — written
// once per mail class, so a new line in the footer has to be added to each.
public sealed class ShippedMail(string customer)
{
    // @sin NearDuplicateMethod
    public string Render(Order order)
    {
        var body = new StringBuilder();
        body.AppendLine($"Hello {customer},");
        body.AppendLine($"Your order {order.Reference} has shipped.");

        foreach (var line in order.Lines)
        {
            body.AppendLine($"- {line.Quantity} x {line.Sku}");
        }

        body.AppendLine("Thank you for shopping with us.");

        return body.ToString();
    }
}
