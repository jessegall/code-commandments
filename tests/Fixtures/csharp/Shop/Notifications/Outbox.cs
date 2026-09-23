namespace Shop.Notifications;

// The outbox sends each waiting mail, and when anything at all goes wrong it simply moves on to the
// next — the mail that failed, and why, are gone.
public sealed class Outbox(Queue<string> waiting, Action<string> send)
{
    public int Flush()
    {
        var sent = 0;

        while (waiting.TryDequeue(out var mail))
        {
            try
            {
                send(mail);
                sent++;
            }
            // @sin SwallowedException
            catch (System.Exception)
            {
                continue;
            }
        }

        return sent;
    }

    public int FlushLoudly(TextWriter log)
    {
        var sent = 0;

        while (waiting.TryDequeue(out var mail))
        {
            try
            {
                send(mail);
                sent++;
            }
            // @righteous SwallowedException
            catch (Exception error)
            {
                log.WriteLine($"Mail could not be sent: {error.Message}");
                throw;
            }
        }

        return sent;
    }
}
