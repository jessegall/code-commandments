namespace Shop.Notifications;

public sealed record HourSpan(int From, int Until)
{
    public bool Contains(int hour) => From <= Until ? hour >= From && hour < Until : hour >= From || hour < Until;
}

// @sin CoupledFields
public sealed class QuietHours
{
    public int? StartHour { get; set; }

    public int? EndHour { get; set; }

    public string Channel { get; set; } = "email";

    public bool Silences(int hour) => StartHour is not null && EndHour is not null && new HourSpan(StartHour.Value, EndHour.Value).Contains(hour);
}
