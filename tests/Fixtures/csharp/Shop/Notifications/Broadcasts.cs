namespace Shop.Notifications;

public sealed class Broadcasts(IReadOnlyList<string> subscribers)
{
    /// <summary>Sends the message.</summary>
    /// <typeparam name="TMessage">The message.</typeparam>
    /// <param name="message">The message.</param>
    /// <returns>The <see cref="int"/>.</returns>
    // @sin CeremonyDocblock
    public int SendMessage<TMessage>(TMessage message) where TMessage : notnull
    {
        return subscribers.Count(subscriber => subscriber.Length > 0 && message.ToString() is { Length: > 0 });
    }

    /// <summary>Sends the message.</summary>
    // @righteous CeremonyDocblock
    public void Send(string message)
    {
        Console.WriteLine(message);
    }
}
