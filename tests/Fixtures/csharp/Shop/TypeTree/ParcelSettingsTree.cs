using System;
using System.Collections.Generic;
using Shop.ExternalTree;

namespace Shop.LocalTree;

// @sin LoneImplementerInterface
public interface IParcelSettingsTreePort
{
    string SendParcelSettingsTree();
}

public sealed class ParcelSettingsTreeExporter : IParcelSettingsTreePort
{
    public string SendParcelSettingsTree() { return "parcel"; }
}

public sealed class ParcelSettingsTreeOwner
{
    private readonly ParcelSettingsTreeImportedService reader;

    public ParcelSettingsTreeOwner(
        // @sin ConcreteCrossModuleDependency
        ParcelSettingsTreeImportedService reader)
    {
        this.reader = reader;
    }

    public string Record() { return this.reader.Read() + "!"; }
}

public class ParcelSettingsTreeSource
{
    public virtual string Read() { return "parcel"; }
    public string Record(string tag) { return tag.Trim(); }
}

// @sin MiddleMan
public sealed class ParcelSettingsTreeForwarder
{
    private readonly ParcelSettingsTreeSource source;
    public ParcelSettingsTreeForwarder(ParcelSettingsTreeSource source) { this.source = source; }
    public string Read() { return this.source.Read(); }
    public string Record(string tag) { return this.source.Record(tag); }
}

public sealed class ParcelSettingsTreeBroken : ParcelSettingsTreeSource
{
    // @sin RefusedBequest
    public override string Read() { throw new NotSupportedException(); }
}

// @sin HandRolledRegistry
public sealed class ParcelSettingsTreeRegistry
{
    private readonly Dictionary<string, string?> entries = new();
    public void Put(string key, string value) { this.entries[key] = value; }
    public string? Read(string key)
    {
        if (this.entries[key] == null)
        {
            throw new KeyNotFoundException();
        }
        return this.entries[key];
    }
}

public sealed record ParcelSettingsTreeLabel(string Text);
public sealed record ParcelSettingsTreeBin(ParcelSettingsTreeLabel Label);
public sealed record ParcelSettingsTreeShelf(ParcelSettingsTreeBin Bin);

public sealed class ParcelSettingsTreeDisplay
{
    public string Read(ParcelSettingsTreeShelf shelf)
    {
        // @sin TrainWreck
        return shelf.Bin.Label.Text;
    }

    // @sin UnusedPrivateMember
    private string Draft() { return "parcel"; }

    public int Weight
    {
        // @sin UselessPropertyHook
        get { return 8; }
    }
}

public enum ParcelSettingsTreeStage { Open, Closed }

public sealed class ParcelSettingsTreeBatch
{
    public ParcelSettingsTreeStage Stage { get; private set; }
    public void Seal() { this.Stage = ParcelSettingsTreeStage.Closed; }
}

public sealed class ParcelSettingsTreeSender
{
    public void Dispatch(ParcelSettingsTreeBatch batch)
    {
        // @sin GuardBesideTransition
        if (batch.Stage != ParcelSettingsTreeStage.Open)
        {
            throw new InvalidOperationException();
        }
        batch.Seal();
    }
}
