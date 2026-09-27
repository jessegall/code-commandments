# What judging needs, per language

code-commandments is one executable. Composer's shim fetches the one for your platform on its first run, checked
against the release's `SHA256SUMS`, and nothing else is installed with it. Each language it judges is parsed by that
language's own parser, through a small bridge the tool carries or fetches, and a bridge leans only on the toolchain of
the language it reads — the one a project in that language already has. Nothing needs Docker.

| Language | What the machine needs | What the tool brings |
|---|---|---|
| PHP | `php` 8.3 or later on the `PATH` | the PHP bridge, with the php-parser it loads; a project's own `vendor/autoload.php`, when there is one, for the classes outside the scan |
| Vue, TypeScript | `node` 18 or later on the `PATH` | the frontend bridge, one bundled script with TypeScript's own compiler inside |
| Python | `python3` 3.10 or later on the `PATH`, with `venv` and `pip`, and the network once | the Python bridge; its first run builds it a virtual environment with the pinned mypy, under the cache folder |
| C# | nothing | the C# bridge, a self-contained executable for your platform, fetched from the release on the first C# judge beside the tool and checked against `SHA256SUMS`; it is never built on your machine |

## When something is missing

A language whose toolchain is not installed is left unjudged, and the run says so once, naming what to install;
every other language is judged as usual:

```
⚠ 12 file(s) left unread — the Python bridge needs python3 on the PATH, which is not installed here, so Python is not judged; everything else is
```

C# needs nothing installed, but reads the framework's types from a .NET SDK when one is: the reference packs of the
SDK that `$DOTNET_ROOT` names, that the `dotnet` on the `PATH` belongs to, or that sits in a folder .NET's installers
use. Without an SDK, C# is still judged, with every type that resolves without the framework's (the project's own,
and its restored NuGet packages), and the run says so once:

```
⚠ no .NET SDK is installed here, so C#'s framework types do not resolve and C# is judged with the types that resolve without them; install the .NET SDK to judge it whole
```

A C# bridge that cannot be fetched — no network on the first C# judge, or a download whose sum does not match — leaves
C# unjudged the same way, saying why. A build of the tool from source has no release to fetch from; name a bridge in
`$COMMANDMENTS_ROSLYN` (this repository's own development sets `COMMANDMENTS_ROSLYN=docker`, which runs it in a capped
container of its image).

## Kept warm

In a session where the agent journal runs the tool as a plugin, `commandments roslyn-serve` keeps the C# bridge running
for the project, so each judge of C# compiles against references already loaded; a run finds it at a socket named for
the project, and starts a bridge of its own when there is none.
