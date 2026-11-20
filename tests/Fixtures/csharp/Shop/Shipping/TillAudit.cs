namespace Shop.Shipping;

public sealed class TillAudit
{
    // @sin DivergentTwin
    public void AuditTill(ICashReconcileSteps job)
    {
        job.OpenTill();
        job.CountNotes();
        job.MatchReceipts();
        job.CloseDrawer();
        job.RecordDifference();
    }

    // @righteous DivergentTwin
    public void CloseWithReceipt(ICashReconcileSteps job, Action<int> record, int drawer)
    {
        job.MatchReceipts();
        record(drawer);
        job.CloseDrawer();
    }
}
