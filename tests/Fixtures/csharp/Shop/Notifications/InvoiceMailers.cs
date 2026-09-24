namespace Shop.Notifications;

public sealed record InvoiceMail(string Recipient, string Subject, decimal Amount);

public sealed class Mailbox
{
    private readonly List<string> sent = [];

    public int Send(string recipient, string subject, decimal amount)
    {
        sent.Add($"{recipient}|{subject}|{amount:0.00}");

        return sent.Count;
    }
}

public sealed class InvoiceMailer(Mailbox mailbox)
{
    // @sin DerivedArgument
    public int Deliver(InvoiceMail mail) => mailbox.Send(mail.Recipient, mail.Subject, mail.Amount);

    public int Remind(InvoiceMail original)
    {
        var reminder = original with { Subject = "Reminder: " + original.Subject };
        // @sin DerivedArgument
        return mailbox.Send(reminder.Recipient, reminder.Subject, reminder.Amount);
    }
}
