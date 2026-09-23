using System.Text;

namespace Shop.Stock;

// A shelf category and the category it sits under.
public sealed record Category(string Name, Category? Parent);

public static class CategoryPaths
{
    public static string Breadcrumb(Category leaf)
    {
        var trail = new Stack<string>();

        // @sin NonCountingFor
        for (var category = leaf; category is not null; category = category.Parent)
        {
            trail.Push(category.Name);
        }

        return string.Join(" / ", trail);
    }

    // @righteous NonCountingFor
    public static string Indent(int depth)
    {
        var text = new StringBuilder();

        for (var level = 0; level < depth; level++)
        {
            text.Append("  ");
        }

        return text.ToString();
    }
}
