namespace Shop.Loyalty;

public sealed class PunchCard
{
    public int Stamps { get; set; }

    public int Redeemed { get; set; }

    public DateOnly? LastRedeemed { get; set; }
}

public sealed class CardDesk
{
    // @sin FeatureEnvy
    public void Redeem(PunchCard card, DateOnly today)
    {
        card.Stamps = 0;
        card.Redeemed++;
        card.LastRedeemed = today;
    }
}
