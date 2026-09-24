# C# templates — a multi-line string shows its output — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `vendor/bin/commandments info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`csharp-assembled-template`** — `string.Join("\n", new[] { "public class X", "{", "}" })` or `sb.AppendLine("…")` line after line — a multi-line text built from line fragments, so its shape cannot be seen in the source — `AssembledTemplateDetector`
