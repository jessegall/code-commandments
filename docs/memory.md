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
tool's findings. It also finds three more, all DanglingDocReference in test projects, whose verdict reads nothing
but the reference facts the Roslyn bridge writes; this snapshot's restore output was made fresh in the capped
container, where the kept PHP answer's snapshot carried the restore output of a built checkout.

