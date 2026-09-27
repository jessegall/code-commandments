# Contributing

## Running Go: only through `scripts/dev`

Every Go build, test, vet, run and generate goes through `scripts/dev`, never Go on the
host. It runs the command in a dev container the machine caps at 3 GB (no swap) and
2 CPUs, so a run that grows past that is killed by the kernel instead of taking the
machine down. `GOMEMLIMIT` alone is only a target.

```
scripts/dev go test ./cli/...        # scope it: the packages you touched
scripts/dev go vet ./bridge/
scripts/dev go generate ./registry
scripts/dev --mount ../some-app go run ./engine/frontend/parity ../some-app
```

- **The image** (`docker/dev/Dockerfile`): Go, PHP for the PHP bridge, composer for
  the consumer and shim tests, node for the frontend bridge, Python with the pinned mypy, git
  and the docker CLI. It is built on the first run and rebuilt
  only when the Dockerfile or the mypy pin changes.
- **Inside**: `GOMEMLIMIT=1200MiB` per process (so the two a `-p=2` test run starts
  feel GC pressure before the 3 GB kill), `GOMAXPROCS=2`, and `go` builds and tests with
  `-p=2 -parallel=2`.
- **Caches**: Go's module and build caches are shared docker volumes, so only the
  first run is slow.
- **Paths**: the checkout is mounted at its own path, and so is a per-run temp
  folder. The host's docker socket is mounted too, so the Roslyn bridge a test
  starts gets paths the host can mount, and runs in its own capped container
  beside the dev one. A folder outside the checkout needs `--mount` (read-only).
- **No docker, no run**: `scripts/dev` fails and says so. There is no host fallback.

`docker/dev/cap_test.go` proves the cap: it has `scripts/dev` start a container of its
own, runs a child past 3 GB in it and expects the kernel's SIGKILL. The kill lands in
that container alone, never on another package's test running beside it at `-p 2`. Scripts and hooks in this repository that run Go call `scripts/dev`
as well.

## The suite

`scripts/dev go test ./the/packages/you/touched`, scoped while iterating; the whole
suite is `scripts/dev go test ./...`, run package by package at `-p 2`.

The suite needs no `composer install`: a fresh clone passes as it stands. A test that
wants a composer-installed project has composer install one of its own in a temp folder
(the shim test), and the parity cases that want an installed package copy the stub
`cli/parity/testdata/installed.json`, which says in its own comment what it stands for.

The PHP tool this one replaced left its answers behind as recordings, and the tests
still hold the Go tool to them: the shop fixture's answers beside the fixture they
are about (`engine/php/testdata/oracle`), the scribes' rewrites, and the real
codebases' findings (`docs/parity.md`). Nothing records them again: a new rule is
proven by its fixture's markers alone.
