namespace Shop;

public sealed class Job : Base
{
    public override void Run() {} // @contract promise/obligation-across-files -> one.cs#base
}
