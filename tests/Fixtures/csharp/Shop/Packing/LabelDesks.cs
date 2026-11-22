namespace Shop.Packing;

public interface ICourierDesk
{
    void Book(string order);
}

// A label desk handed a courier it never asks for anything.
// @sin UnreadCollaborator
public sealed class LabelDesk(ICourierDesk courier)
{
    public int Print(string order) => order.Length;
}
