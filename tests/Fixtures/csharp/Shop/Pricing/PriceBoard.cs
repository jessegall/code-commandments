namespace Shop.Pricing;

public sealed class Display
{
    private string shown = "";

    public void Show(string line) => shown = line;

    public string Shown() => shown;
}

// The price board by the till, showing the price of the day.
public sealed class PriceBoard
{
    private readonly Display display;

    private readonly int pence;

    public PriceBoard(Display display, int pence)
    {
        this.display = display;
        this.pence = pence;

        // @sin ConstructorSideEffect
        this.display.Show($"{pence}p");
    }

    public int Pence() => pence;
}
