#!/usr/bin/env bash
# The Roslyn bridge as the service a session keeps up: one detached container for the project, capped at two cores
# and 4 GB, the project mounted read-only, answering on a local port. The journal starts this with the session and
# stops it with the session; the script stays in the foreground while the container runs, and stops the container
# when it is itself stopped. .NET never runs on the host.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
image="$(cat "$here/IMAGE")"
project="$(cd "${CLAUDE_PROJECT_DIR:-$PWD}" && pwd -P)"
name="code-commandments-roslyn-$(printf '%s' "$project" | shasum | cut -c1-12)"

if ! docker image inspect "$image" > /dev/null 2>&1; then
    echo "the C# bridge image $image is not installed. It is built once per release, never on demand: docker build -t $image bridge/roslyn" >&2
    exit 3
fi

packages="${NUGET_PACKAGES:-$HOME/.nuget/packages}"
mounts=(-v "$project:$project:ro")
[ -d "$packages" ] && mounts+=(-v "$packages:$packages:ro")

docker rm -f "$name" > /dev/null 2>&1 || true
docker run -d --rm --memory=4g --cpus=2 --name "$name" \
    --label code-commandments.roslyn=service --label "code-commandments.project=$project" \
    -p 127.0.0.1::7070 -e NUGET_PACKAGES="$packages" "${mounts[@]}" "$image" --tree --listen 7070 > /dev/null

trap 'docker stop "$name" > /dev/null 2>&1 || true' EXIT INT TERM
docker wait "$name" > /dev/null &
wait "$!"
