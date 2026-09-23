namespace Shop.Notifications;

public sealed class MailingList
{
    private readonly List<string> members = [];

    public void Join(string address) => members.Add(address);

    public int Count() => members.Count;
}

// A customer's newsletter preference, built from their account.
public sealed class NewsletterPreference
{
    private readonly string address;

    public NewsletterPreference(string address, MailingList list)
    {
        this.address = address;

        // @sin ConstructorSideEffect
        list.Join(address);
    }

    public string Address() => address;
}

// @fixed ConstructorSideEffect
public sealed class NewsletterSignup(string address, MailingList list)
{
    public void Confirm() => list.Join(address);
}
