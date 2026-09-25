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

- **The image** (`docker/dev/Dockerfile`): PHP and composer, Go, node, Python with
  the pinned mypy, git and the docker CLI. It is built on the first run and rebuilt
  only when the Dockerfile or the mypy pin changes.
- **Inside**: `GOMEMLIMIT=3GiB`, `GOMAXPROCS=2`, and `go` builds and tests with
  `-p=2 -parallel=2`.
- **Caches**: Go's module and build caches are shared docker volumes, so only the
  first run is slow.
- **Paths**: the checkout is mounted at its own path, and so is a per-run temp
  folder. The host's docker socket is mounted too, so the Roslyn bridge a test
  starts gets paths the host can mount, and runs in its own capped container
  beside the dev one. A folder outside the checkout needs `--mount` (read-only).
- **PHP dependencies**: a checkout without `vendor/` has it installed in the
  container on the first run. A worktree takes the main checkout's `composer.lock`,
  which the recorded answers were made against.
- **No docker, no run**: `scripts/dev` fails and says so. There is no host fallback.

`docker/dev/cap_test.go` proves the cap: it runs a child past 3 GB and expects the
kernel's SIGKILL. Scripts and hooks in this repository that run Go call `scripts/dev`
as well.

## The PHP suite

`vendor/bin/phpunit tests`, scoped to the tests you touched while iterating.
