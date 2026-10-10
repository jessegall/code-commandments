#!/usr/bin/env bash
# Runs the Roslyn bridge once, in its memory-capped container: .NET never runs on the host.
#
#   roslyn-in-docker.sh <folder>[:ro]... -- <bridge arguments>
#
# The image is the one IMAGE beside this script names, built once per release and never here. Each folder is mounted
# at its own path, a read-only one widened to the git repository that holds it, so a path reads the same inside the
# container as outside and a project compiles with the projects it references; the NuGet cache is mounted the same
# way, read-only, so a project's restored packages resolve where its assets file says. Two cores and 4 GB at most;
# the container is named and labelled as the bridge's own, and stopped when the run, or the process that started it,
# ends.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
image="$(cat "$here/IMAGE")"

if ! docker image inspect "$image" > /dev/null 2>&1; then
    echo "the C# bridge image $image is not installed. It is built once per release, never on demand: scripts/roslyn-image" >&2
    exit 3
fi

packages="${NUGET_PACKAGES:-$HOME/.nuget/packages}"
mounts=()
mounted=""
[ -d "$packages" ] && mounts+=(-v "$packages:$packages:ro")

while [ "$#" -gt 0 ] && [ "$1" != "--" ]; do
    folder="${1%:ro}"
    mode=""
    [ "$folder" != "$1" ] && mode=":ro"
    # A project compiles with the projects it references, anywhere in its repository: a read-only folder is widened
    # to the repository that holds it.
    if [ -n "$mode" ] && repository="$(git -C "$folder" rev-parse --show-toplevel 2> /dev/null)"; then
        folder="$repository"
    fi
    case "$mounted" in
        *"|$folder|"*) ;;
        *) mounts+=(-v "$folder:$folder$mode"); mounted="$mounted|$folder|" ;;
    esac
    shift
done
[ "$#" -gt 0 ] && shift

# A container outlives a docker client whose caller died, compiling for no one until it next reads a request: the
# run keeps the client as its child and stops the container when it ends, however it ends, and when its caller does.
name="code-commandments-roslyn-run-$$-$RANDOM"
caller=$PPID
# An asynchronous command reads /dev/null unless handed the run's input, kept on a descriptor of its own first.
exec 3<&0
docker run --rm -i --init --memory=4g --cpus=2 \
    --name "$name" --label code-commandments.roslyn=run \
    -e NUGET_PACKAGES="$packages" "${mounts[@]}" "$image" "$@" <&3 &
client=$!
exec 3<&-
(
    while kill -0 "$caller" 2> /dev/null; do
        sleep 1
    done
    docker kill "$name"
) < /dev/null > /dev/null 2>&1 &
watcher=$!
trap 'kill "$watcher" 2> /dev/null; kill -0 "$client" 2> /dev/null && docker kill "$name" > /dev/null 2>&1; true' EXIT
trap 'exit 143' INT TERM HUP
wait "$client"
