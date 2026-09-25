namespace App;

public sealed class Receipt
{
    public string Line(decimal total) => total.ToString();
}
