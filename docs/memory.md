# What judging a whole codebase costs in memory

The Go engine holds every file's tree for the whole run, because cross-file rules (the namespace graph, callers,
declared types) read the whole program. This page records what that costs on two real codebases, measured the same
way each time, so a change to how trees are held is judged by numbers rather than by feel.

## How it is measured

Every run is under the agent limits, `GOMEMLIMIT=3GiB GOMAXPROCS=2`, on an Apple M4 with 24 GB (macOS 15.6).

- `scripts/memory/snapshot.sh <checkout> <commit> <out>` pins a codebase: the committed files at one commit,
  archived without touching the checkout's working copy. For a C# solution it adds what the Roslyn bridge reads:
  the restore output, made by `dotnet restore` in a memory-capped container (`--memory=4g --cpus=2`), never on the
  host, and the generated sources a build writes, copied from the checkout.
- `scripts/memory/measure` loads the snapshot through the same scan `judge` uses, then reports the files and nodes
  it holds, the heap once garbage is collected (and so bytes per node), and the heap's peak while every detector of
  the codebase's languages runs. `-heap <file>` writes a heap profile of the loaded codebase; `-each` reads a C#
  solution one project folder at a time.
- `scripts/memory/judge.sh <snapshot>` judges the whole snapshot with every detector inside the capped dev
  container (3 GB, no swap, 2 CPUs) under `GOMEMLIMIT=3GiB`, and prints the time, the load, the findings and the
  container's peak memory as its cgroup counts it: the engine and the PHP, node and mypy bridges it runs. A run past
  the cap is killed by the kernel. The Roslyn bridge runs in its own capped container.
- `scripts/memory/peak.sh <command>` prints the command's peak physical footprint, compressed pages included, and
  kills it past 4 GiB. Resident set size is not used: macOS compresses a busy process's pages, and a Chronos load
  that held 3.7 GB showed 500 MiB resident. The bridges run as separate processes (PHP, node, and the Roslyn bridge
  in its own capped container), so the figure is the Go engine's own.

## Baseline

At 1a29061ff's engine, before any compaction. The C# bridge answered from one capped service container for the
snapshot (`bridge/roslyn/roslyn-service.sh`), so each project's load is the engine's work and not a container start.

| Codebase | Commit | Files | Nodes | Settled heap | Bytes/node | Peak heap judging | Peak footprint | Load |
|---|---|---|---|---|---|---|---|---|
| smart-farmers-pos (PHP, Vue, TypeScript), whole | 8ca4e821c | 8,505 | 1,837,305 | 1,431 MiB | 816 | 3,062 MiB | 3,312 MiB | 117 s |
| Chronos (C#), whole solution | ac38efd04 | 14,790 | 5,785,866 | — | — | — | killed at 4,117 MB, still loading | 1,296 s |
| Chronos, one project at a time: the largest (tests/Application.Tests.Feature) | ac38efd04 | 581 | 685,517 | 495 MiB | 757 | 1,160 MiB | 1,208 MiB over all 212 | 37 s |

Neither codebase judges whole under the limit today. smart-farmers-pos goes past 3 GiB while its detectors run.
Chronos never finishes loading: at ~760 bytes a node its 5.8 million nodes are ~4.2 GB before a detector runs, and
the run spends a quarter of its time in the kernel as the collector fights the limit. Files and nodes for the whole
solution are the sum over its projects.

Where the settled heap goes (heap profile after load):

| Holder | smart-farmers-pos | Chronos, largest project |
|---|---|---|
| Node, Type and slice structs decoded from the stream (`reflect.unsafe_New`, `reflect.growslice`) | 857 MB | 310 MB |
| Strings decoded from the stream (`literalStore`) | 167 MB | 95 MB |
| PHP resolved types the engine fills | 31 MB | — |
| Parent links and the per-file node index (`contract.link`) | 19 MB | 7 MB |

The trees are nearly all of what is held once a codebase is loaded: about 450 bytes of structs a node and, in C#,
another 140 of strings, most of them the same symbol and type text again and again. The detectors' whole-program
analyses, kept on the codebase for the run, then roughly double it at the peak.

## Compacting the trees

Each step measured on the same snapshots; the load average (1, 5 and 15 minutes, on 10 cores) is recorded beside
each load time, because the machine is shared and a load time means little without it.

| Step | Commit | smart-farmers-pos: bytes/node, settled, peak heap, peak footprint, load | Chronos largest project: bytes/node, settled, peak heap, peak footprint, load |
|---|---|---|---|
| Baseline | 1a29061ff | 816, 1,431 MiB, 3,062 MiB, 3,312 MiB, 117 s | 757, 495 MiB, 1,160 MiB, —, 37 s |
| Facts held off the node | 0b8d27579 | 681, 1,195 MiB, 2,867 MiB, 3,114 MiB, 407 s (load ~18) | — (the run's bridge stalled; to-do 15) |
| Strings, lists, types and targets interned per stream | 943ecf984 | 442, 775 MiB, 1,864 MiB, 2,077 MiB, 61 s (load 6–10) | 348, 228 MiB, 544 MiB, 569 MiB, 38 s (load 6–10) |
| The schema checked in tests only | 0bd335bf7 | 411, 722 MiB, 1,837 MiB, 2,024 MiB, 70 s (load 12 15 16) | 322, 211 MiB, 562 MiB, 588 MiB, 122 s (load 18 15 16) |

smart-farmers-pos now judges whole under the limit, at two thirds of it. What is left of a node is its own
struct: after interning, a heap profile of Chronos's largest project holds 152 MB of node, facts and type structs,
6 MB of parent links, 6 MB of slices and 3.5 MB of strings. Packing the span and id would only move a node from
the 176-byte size class to 160, so it is not done. At 322 bytes a node, Chronos's 5.8 million nodes are still
about 1.8 GB before a detector runs, and the detectors' analyses more than double the heap: the whole solution
needs its projects loaded one at a time, with the facts that cross projects kept as summaries.

The compaction changes no finding: the per-project Chronos parity on the snapshot finds all 15,023 of the PHP
tool's findings (see *Every finding accounted for* below for the three it found beyond them).

## Judging a whole solution

Compaction alone leaves Chronos at about 1.8 GB of trees, so a C# solution is judged a project at a time. The
Roslyn bridge is asked once for the whole solution; it compiles each project once, dependencies first, and streams
them project by project. As the stream is read it is cut into one unit per project — a file belongs to the deepest
folder above it holding a `.csproj` — each kept gzipped on disk, and each unit's summary is taken as it closes:
every fact a rule asks of the whole program, as names, counts and flags (`engine/csharp/summary.go`). The summaries
merge, and each unit is then read back alone and judged with the merged summary as its program, so a rule reading
the program answers as it would over the whole solution. The rules that weigh candidates across the program
(duplicates, data clumps, repeated guards and calls, converted and derived arguments) hand over a record per
candidate, and decide over every unit's records once the last is read. Every other language is read whole, and
every bridge's stream is decoded as it is written.

`TestJudgingTheFixtureInHalvesFindsWhatJudgingItWholeFinds` holds this to the whole answer: the C# fixture judged in
two halves finds, for every C# rule, exactly what the fixture judged whole finds.

| Codebase | Commit | Files | Findings | Time | Peak memory in the capped dev container |
|---|---|---|---|---|---|
| smart-farmers-pos (PHP, Vue, TypeScript) | 8ca4e821c | 8,505 | 3,695 | 83 s (load 1.9) | 2,283,266,048 bytes (2.13 GiB) |
| Chronos (C#, three Python scripts) | ac38efd04 | 14,790 | 15,789 | 590 s (load 6.3–9.1) | 1,106,927,616 bytes (1.03 GiB) |

Before this work neither judged whole under the limit: smart-farmers-pos peaked at 3,312 MiB and Chronos was
killed still loading at 4,117 MB.

## Every finding accounted for

The PHP tool's pinned answer for Chronos (`engine/csharp/testdata/chronos.findings.each.gz`) judges each project on
its own; judging the whole solution at once does not fit in memory for the PHP tool, so the whole answer has no PHP
counterpart. The 15,789 findings the Go tool makes judging all of Chronos at once break down against it as follows.

- **5** are in the three Python scripts under `tools/`; the rest, **15,784** lines, are C#, 15,715 distinct once a
  place two nodes of one rule flag is counted once, as the PHP answer counts it.
- **165** of the PHP answer's findings stand in files marked `<auto-generated/>` (`src/Api.Client/Sagas/Generated`,
  `src/Host/Internal/Generated`). `judge` reads a generated file but never reports a sin in it; the script that
  records the PHP answer does not ask.
- **863** are made only judging the whole solution, and **6** only project by project, all by rules that weigh the
  whole program: the same body, guard, clump or call across projects (DuplicateMethod 410, NearDuplicateMethod 90,
  RepeatedGuard 79, DataClump 72, ConvertedArgument 14, RepeatedNamedCall 2), an enum, record or type declared in
  another project (EnumCaseOrChain 102, StringMirrorsEnum 29, TypeSwitch 11, PlaceholderFilledData 9,
  MatchDefaultReturnsNull 9, ConstClassEnum 2, InArrayMirrorsEnum 1, CoupledFields 1), a parameter whose type
  another project declares (FeatureEnvy: 27 more, and 4 fewer where a second parameter's type now counts as the
  program's own), callers and spellings in other projects (DeNulledFinder 1, UnnamedVocabularyLiteral 1,
  DerivedArgument 1 fewer), and a near copy whose exact twin is in another project (NearDuplicateMethod 1 fewer).
- **3** are DanglingDocReference findings in test projects whose doc references name a type in a project they do
  not reference. The PHP answer missed them because the Roslyn bridge's container could read only the folder
  asked for, so a project whose references stood outside it compiled with types unresolved, and a file the
  compiler cannot fully resolve is blind. Both launchers now mount every project a project references (894c193f6),
  and the PHP answer is recorded again with them.

