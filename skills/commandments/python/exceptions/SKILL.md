---
name: commandments-python-exceptions
description: "Writing a `raise`, a `try`/`except`, or an exception class in Python — or deciding what a function does when something goes wrong. Read this BEFORE you write `except Exception: pass`, an `except` that returns `None`/`[]`/`False`, `raise ValueError(\"…\")` with a message built at the raise, or a `raise` inside an `except` without `from`."
---

# Python exceptions — fail loud, named, at the source

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> **Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual
> failure is a five-minute fix. A swallowed one is a silent wrong result you chase for a week.

## The principle

A failure is information. The instant it happens it knows the most it will ever know — *what*
broke and *with what values*. Raise that knowledge **loudly, by type, at the source**. Every line
between the failure and its surfacing — an `except` that returns `None`, a default that papers
over it, a bare `ValueError("...")` — destroys information and moves the debugging session
further from the cause.

### Never swallow a failure

`except Exception: pass`, `except KeyError: return None`, `except OSError: return []` turn a
failure into a value the caller cannot tell from a real answer. If the failure is expected and
has a meaning, catch the **specific** exception and do the thing that meaning calls for; if it is
not, let it propagate.

### Name the failure, and let its class write the message

A bare builtin raised with a message — `raise ValueError(f"unknown carrier {name}")` — says
nothing a caller can catch by meaning, and every raise site re-words the same failure. Give the
failure a class of its own, and give that class a **classmethod factory** that takes the values
and writes the message once:

```python
class UnknownCarrier(LookupError):
    @classmethod
    def named(cls, name: str) -> "UnknownCarrier":
        return cls(f"no carrier is registered as {name!r}")


raise UnknownCarrier.named(name)
```

### Keep the cause

A `raise` inside an `except` that translates one failure into another says where it came from:
`raise ImportFailed.of(path) from error`. Without `from`, the original is reported as an
accident that happened while handling it.

### The one place you tolerate: a named outer boundary

Fail-hard does **not** mean every layer re-raises forever. Failures travel *up* to **one explicit
boundary** allowed to absorb them — a request handler, a worker loop, a decoder of untrusted
input — and even there absorbing is **observable**: it logs, counts or reports, never silently
continues.

## Rules

- [ ] Never swallow every failure: catch the one you expect and act on it, or let it propagate to a boundary that records it.
      _Name the exception you expect (`except ValueError:`) and do what its meaning calls for; anything else propagates. At a real boundary, log or report before moving on._

## Worked example

### python-swallowed-exception

A bare `except:` or `except Exception` whose body only passes, continues or returns nothing — every failure, expected or not, made to vanish

```py
----------[ Bad ]----------

def load_catalog(path: Path) -> dict:
    try:
        return json.loads(path.read_text())
    except Exception:
        return {}

----------[ Good ]----------

# in catalog_file.py
class CatalogUnreadable(Exception):
    @classmethod
    def at(cls, path: Path) -> "CatalogUnreadable":
        return cls(f"the catalog at {path} is not valid JSON")

# in catalog_file.py
def read_catalog(path: Path) -> dict:
    try:
        return json.loads(path.read_text())
    except json.JSONDecodeError as error:
        raise CatalogUnreadable.at(path) from error
```

## Commands

- `vendor/bin/commandments judge --skill=python/exceptions` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-swallowed-exception`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/exceptions`](../../backend/exceptions/SKILL.md) — the same discipline on the PHP backend, with `::for()` factories.
- [`backend/absence`](../../backend/absence/SKILL.md) — whether "missing" is a failure to raise at all, or a value to model.
