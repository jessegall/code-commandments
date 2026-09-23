namespace Shop.Orders;

public sealed record Customer(string Name, string? Email);

// A customer is looked up by name and handed back with `!` — the lookup may miss, the compiler said so,
// and the `!` moves the NullReferenceException to whoever reads the result.
public sealed class Customers(IReadOnlyList<Customer> all)
{
    public Customer Named(string name)
    {
        var found = all.FirstOrDefault(customer => customer.Name == name);

        // @sin NullForgiven
        return found!;
    }

    // @fixed NullForgiven
    public Customer Existing(string name) =>
        all.FirstOrDefault(customer => customer.Name == name) ?? throw UnknownCustomer.Named(name);
}

// @fixed NullForgiven
public sealed class UnknownCustomer : InvalidOperationException
{
    private UnknownCustomer(string message) : base(message) {}

    public static UnknownCustomer Named(string name) => new($"No customer is called '{name}'.");
}
