namespace Shop.Documents;

public sealed record LabelTemplate(string Name, int WidthMm, int HeightMm);

public sealed class TemplateShelf
{
    private readonly List<LabelTemplate> templates = [];

    // @sin DeNulledFinder
    public LabelTemplate? Named(string name) => templates.Find(template => template.Name == name);

    // @righteous DeNulledFinder
    public LabelTemplate? Largest() => templates.MaxBy(template => template.WidthMm * template.HeightMm);
}

public sealed class LabelLayout(TemplateShelf shelf)
{
    // @sin InlineThrow
    public int Width(string name) => (shelf.Named(name) ?? throw new KeyNotFoundException(name)).WidthMm;

    public int Area(string name)
    {
        var template = shelf.Named(name) ?? throw new KeyNotFoundException(name);

        return template.WidthMm * template.HeightMm;
    }

    public string Biggest() => shelf.Largest()?.Name ?? "none";

    public bool HasAny() => shelf.Largest() is not null;
}
