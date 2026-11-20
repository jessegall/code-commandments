namespace Shop.Flow;

public sealed record LabelDetails(string Text, bool Paid, bool Checked);

public sealed class ArgumentContracts
{
    // @fixed ComputedBooleanArgument
    public static bool CanDispatch(LabelDetails label)
    {
        return label.Paid && label.Checked;
    }

    // @righteous ComputedBooleanArgument
    public bool Accept(LabelDetails label)
    {
        return CanDispatch(label);
    }

    public string ChooseTitle(string title,
        // @righteous UnusedParameter
        string prefix)
    {
        return prefix + title;
    }

    // @fixed UnusedParameter
    public string PrintTitle(string title)
    {
        return title.Trim();
    }

    // @righteous HandRolledWither
    public LabelDetails Approve(LabelDetails label)
    {
        return label with { Checked = true };
    }

    // @fixed HandRolledWither
    public LabelDetails Pay(LabelDetails label)
    {
        return label with { Paid = true };
    }

    // @righteous KeyedLookupEnvy
    public string CompareLabel(LabelDetails label, string prefix)
    {
        return prefix + label.Text;
    }

    // @fixed KeyedLookupEnvy
    public string PrintLabel(LabelDetails label)
    {
        return label.Text;
    }

    // @righteous ScratchStateRestore
    public string ProbeLabel(LabelDetails label)
    {
        var saved = label.Text;
        var caption = saved.Trim();
        return caption;
    }

    // @fixed ScratchStateRestore
    public string InspectLabel(LabelDetails label, string probe)
    {
        return probe + label.Text;
    }
}
