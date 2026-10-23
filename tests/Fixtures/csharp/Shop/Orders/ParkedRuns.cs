namespace Shop.Orders;

public enum RunState
{
    Parked,
    Waiting,
    Running,
}

public sealed record Run(RunState State);

// Finding the runs a sweep should pick up. The test says the states by name because the lambda becomes a query the
// store translates, where a method of ours cannot be called: a named group would have to run, and nothing here
// runs it.
public sealed class ParkedRuns
{
    // @righteous EnumCaseOrChain
    public IQueryable<Run> Of(IQueryable<Run> runs) =>
        runs.Where(run => run.State == RunState.Parked || run.State == RunState.Waiting);

    // @sin EnumCaseOrChain
    public bool Sweeps(Run run) => run.State == RunState.Parked || run.State == RunState.Waiting;
}
