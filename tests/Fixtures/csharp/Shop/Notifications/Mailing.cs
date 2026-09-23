using Shop.Orders;

namespace Shop.Notifications;

// The mailing list keeps the customers who gave an address — it filters out the nulls, then tells the
// compiler with `!` what the filter already proved, instead of letting the filter say it.
public sealed class Mailing(IReadOnlyList<Customer> customers)
{
    // @sin NullForgiven
    public IReadOnlyList<string> Emails() => customers.Where(customer => customer.Email != null).Select(customer => customer.Email!).ToList();

    // @fixed NullForgiven
    public IReadOnlyList<string> Addresses() => customers.Select(customer => customer.Email).OfType<string>().ToList();
}
