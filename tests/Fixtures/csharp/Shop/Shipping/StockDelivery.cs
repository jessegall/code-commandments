namespace Shop.Shipping;

public interface IStockDeliverySteps
{
    void ScanCrate();
    void Unpack();
    void CheckQuantity();
    void UpdateStock();
    void NotifyBuyer();
}

public sealed class StockDelivery
{
    // @sin DivergentTwin
    public void ReceiveStock(IStockDeliverySteps job)
    {
        job.ScanCrate();
        job.Unpack();
        job.CheckQuantity();
        job.UpdateStock();
    }

    // @sin DivergentTwin
    public void ReceiveOrderStock(IStockDeliverySteps job)
    {
        job.ScanCrate();
        job.Unpack();
        job.CheckQuantity();
        job.UpdateStock();
        job.NotifyBuyer();
    }
}
