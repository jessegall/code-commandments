namespace Shop.Notifications;

public sealed record CampaignSegment(string Name, IReadOnlyList<string> Emails);

public sealed class CampaignPlan
{
    public IReadOnlyList<CampaignSegment> Segments { get; init; } = [];

    public CampaignSegment Segment(string name) => Segments.First(segment => segment.Name == name);
}

public sealed class CampaignSender
{
    // @sin ParamResolvedFromParam
    public int Send(CampaignPlan plan, string segmentName, string subject)
    {
        var segment = plan.Segment(segmentName);

        return segment.Emails.Count(email => email.Contains('@') && subject.Length > 0);
    }
}
