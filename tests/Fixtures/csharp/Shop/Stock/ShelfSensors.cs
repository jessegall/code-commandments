namespace Shop.Stock;

public sealed class SensorHub
{
    private readonly List<string> shelves = [];

    public void Register(string shelf) => shelves.Add(shelf);

    public string Reading(string shelf) => shelves.Contains(shelf) ? "ok" : "unknown";
}

// A weight sensor under one shelf.
public sealed class ShelfSensor
{
    private readonly string shelf;

    private readonly string reading;

    public ShelfSensor(string shelf, SensorHub hub)
    {
        this.shelf = shelf;

        // @sin ConstructorSideEffect
        hub.Register(shelf);

        // @righteous ConstructorSideEffect
        reading = hub.Reading(shelf);
    }

    public string Shelf() => shelf;

    public string Reading() => reading;
}
