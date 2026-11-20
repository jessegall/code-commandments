namespace Shop.Shipping;

public interface ICashReconcileSteps
{
    void OpenTill();
    void CountNotes();
    void MatchReceipts();
    void CloseDrawer();
    void RecordDifference();
}

public sealed class CashReconcile
{
    // @sin DivergentTwin
    public void ReconcileTill(ICashReconcileSteps job)
    {
        job.OpenTill();
        job.CountNotes();
        job.MatchReceipts();
        job.CloseDrawer();
    }
}
