using System.Collections;
using System.Text.Json;

namespace CodeCommandments.Bridge;

/// <summary>One node of the version 5 tree before it is numbered: its kind, byte span, line, facts, owned parts and links.</summary>
internal sealed class TreeNode(string kind, int start, int end, int line)
{
    public string Kind { get; } = kind;

    public int Start { get; } = start;

    public int End { get; } = end;

    public int Line { get; } = line;

    public int Id { get; private set; } = -1;

    public Dictionary<string, object> Facts { get; } = [];

    /// <summary>Each role's node, or its list of nodes.</summary>
    public Dictionary<string, object> Parts { get; } = [];

    /// <summary>Each link's node, or its list of nodes.</summary>
    public Dictionary<string, object> Links { get; } = [];

    /// <summary>Sets a fact, left out when it says nothing: null, false, an empty text or an empty list.</summary>
    public TreeNode Fact(string name, object? value)
    {
        if (value is not (null or false or "") && value is not ICollection { Count: 0 })
        {
            Facts[name] = value;
        }

        return this;
    }

    /// <summary>Sets a fact whatever its value, for one whose false or empty value is still said: a literal's.</summary>
    public TreeNode Said(string name, object value)
    {
        Facts[name] = value;

        return this;
    }

    public TreeNode Holds(string role, TreeNode? node)
    {
        if (node is not null)
        {
            Parts[role] = node;
        }

        return this;
    }

    public TreeNode HoldsAll(string role, IEnumerable<TreeNode> nodes)
    {
        var held = nodes.ToList();

        if (held.Count > 0)
        {
            Parts[role] = held;
        }

        return this;
    }

    public TreeNode Link(string name, object target)
    {
        Links[name] = target;

        return this;
    }

    /// <summary>Every node it owns, in source order.</summary>
    public IEnumerable<TreeNode> Children() => Parts.Values
        .SelectMany(held => held is TreeNode one ? [one] : (List<TreeNode>)held)
        .OrderBy(child => child.Start)
        .ThenByDescending(child => child.End);

    /// <summary>Numbers the tree in pre-order, children in source order, and lists its nodes by number.</summary>
    public List<TreeNode> Numbered()
    {
        var order = new List<TreeNode>();
        var pending = new Stack<TreeNode>();
        pending.Push(this);

        while (pending.Count > 0)
        {
            var node = pending.Pop();
            node.Id = order.Count;
            order.Add(node);

            foreach (var child in node.Children().Reverse())
            {
                pending.Push(child);
            }
        }

        return order;
    }

    public void Write(Utf8JsonWriter json)
    {
        json.WriteStartObject();
        json.WriteNumber("id", Id);
        json.WriteString("kind", Kind);
        json.WriteStartArray("span");
        json.WriteNumberValue(Start);
        json.WriteNumberValue(End);
        json.WriteNumberValue(Line);
        json.WriteEndArray();

        foreach (var (name, value) in Facts)
        {
            json.WritePropertyName(name);
            Value(json, value);
        }

        if (Parts.Count > 0)
        {
            json.WriteStartObject("parts");

            foreach (var (role, held) in Parts)
            {
                json.WritePropertyName(role);

                if (held is TreeNode one)
                {
                    one.Write(json);
                    continue;
                }

                json.WriteStartArray();

                foreach (var child in (List<TreeNode>)held)
                {
                    child.Write(json);
                }

                json.WriteEndArray();
            }

            json.WriteEndObject();
        }

        if (Links.Count > 0)
        {
            json.WriteStartObject("links");

            foreach (var (name, target) in Links)
            {
                if (target is TreeNode one)
                {
                    json.WriteNumber(name, one.Id);
                    continue;
                }

                json.WriteStartArray(name);

                foreach (var each in (List<TreeNode>)target)
                {
                    json.WriteNumberValue(each.Id);
                }

                json.WriteEndArray();
            }

            json.WriteEndObject();
        }

        json.WriteEndObject();
    }

    /// <summary>A fact's value: a text, a yes or no, a number, a map or a list of them.</summary>
    public static void Value(Utf8JsonWriter json, object value)
    {
        switch (value)
        {
            case string text:
                json.WriteStringValue(text);
                break;
            case bool yes:
                json.WriteBooleanValue(yes);
                break;
            case int number:
                json.WriteNumberValue(number);
                break;
            case IDictionary<string, object> map:
                json.WriteStartObject();

                foreach (var (name, held) in map)
                {
                    json.WritePropertyName(name);
                    Value(json, held);
                }

                json.WriteEndObject();
                break;
            case IEnumerable items:
                json.WriteStartArray();

                foreach (var item in items)
                {
                    Value(json, item);
                }

                json.WriteEndArray();
                break;
        }
    }
}
