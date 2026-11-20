namespace Shop.Flow;

// ParcelReturns shelves and refunds the parcels customers send back.
public sealed class ParcelReturns
{
    public List<string> Log { get; } = new();

    public int Seconds { get; private set; }

    public int Fresh { get; private set; }

    public void Restock(ReturnedParcel parcel)
    {
        // @sin DuplicatedBranchTail
        if (parcel.Opened)
        {
            Seconds++;
            Log.Add(parcel.Reason);
        }
        else
        {
            Fresh++;
            Log.Add(parcel.Reason);
        }
    }

    public int Refund(int cents)
    {
        if (cents <= 0)
        {
            throw new NothingToRefund(cents);
            // @sin UnreachableStatement
            // @sin ReassignedParameter
            cents = 0;
        }

        return cents;
    }

    // @fixed DuplicatedBranchTail
    public void RestockOnce(ReturnedParcel parcel)
    {
        if (parcel.Opened)
        {
            Seconds++;
        }
        else
        {
            Fresh++;
        }
        Log.Add(parcel.Reason);
    }

    public string RefundNote(string reason)
    {
        // @sin ReassignedParameter
        reason = reason.Trim().ToLowerInvariant();
        return $"refund: {reason}";
    }
}

// ReturnedParcel is a parcel a customer sent back, with the reason they gave.
public sealed record ReturnedParcel(string Reason, bool Opened);

// NothingToRefund says a refund was asked for an amount that is not above zero.
public sealed class NothingToRefund(int cents) : Exception($"There is nothing to refund for {cents} cents.");
