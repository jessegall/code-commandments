namespace Shop.Shipping;

public interface IParcelReadinessSteps
{
    void Weigh();
    void Wrap();
    void Label();
    void Stage();
    void Insure();
}

public sealed class ParcelReadiness
{
    // @sin DivergentTwin
    public void DispatchParcel(IParcelReadinessSteps job)
    {
        job.Weigh();
        job.Wrap();
        job.Label();
        job.Stage();
    }

    // @sin DivergentTwin
    public void DispatchInsuredParcel(IParcelReadinessSteps job)
    {
        job.Weigh();
        job.Wrap();
        job.Label();
        job.Stage();
        job.Insure();
    }

    // @fixed DivergentTwin
    public void SendInsured(IParcelReadinessSteps job)
    {
        DispatchInsuredParcel(job);
    }
}
