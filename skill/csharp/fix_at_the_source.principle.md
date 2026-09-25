### Trace it back before you change a line

A finding is a symptom. Before adding an `is null` check, a `?? default` or a `try` where it showed up, ask
where the value came from and follow it back to the code that produced it. Fix that code, and the check
you were about to write — and every copy of it — is no longer needed.

### A constructor sets up; it doesn't act

A constructor says what the object is: it stores what it was given and works out what it needs. When it
calls a collaborator to do something — warm a cache, register itself, open a connection — and ignores
the result, just creating the object changes something outside it, in a line that looks like setup. Keep
the collaborator in a field and call it from the method someone calls, when they choose to.

```csharp
public sealed class Report(Printer printer)
{
    public void Start() => printer.Print("start");
}
```

### State that changes lives on an instance

A `static` field that methods write to is state every caller shares and nobody passes in. Who changed it,
and when, isn't written anywhere. Keep changing state on an instance, pass that instance to the code that
needs it (usually through the constructor), and the dependency is in the signature where a reader sees it.