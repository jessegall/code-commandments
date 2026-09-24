# C# pass the object — demand what you use, not an id and its container — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-converted-argument`** — a scalar parameter its callers keep filling with the same conversion — `ReceiptFor(order.Id.ToString())` call after call — because it asks for the converted form instead of the value — `ConvertedArgumentDetector`
- **`csharp-derived-argument`** — a call that hands over an object and a projection of it — `Persist(request, request.ChannelId)` — or an object in three pieces, where the method could read them itself — `DerivedArgumentDetector`
