#!/usr/bin/env bash
# Runs the Roslyn bridge once, in its memory-capped container: .NET never runs on the host.
#
#   roslyn-in-docker.sh <folder>[:ro]... -- <bridge arguments>
#
# The image is the one IMAGE beside this script names, built once per release and never here. Each folder is mounted
# at its own path, so a path reads the same inside the container as outside, and the NuGet cache is mounted the same
# way, read-only, so a project's restored packages resolve where its assets file says. Two cores and 4 GB at most;
# the container is named and labelled as the bridge's own, and removed when the run ends.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
image="$(cat "$here/IMAGE")"

if ! docker image inspect "$image" > /dev/null 2>&1; then
    echo "the C# bridge image $image is not installed. It is built once per release, never on demand: docker build -t $image bridge/roslyn" >&2
    exit 3
fi

packages="${NUGET_PACKAGES:-$HOME/.nuget/packages}"
mounts=()
[ -d "$packages" ] && mounts+=(-v "$packages:$packages:ro")

while [ "$#" -gt 0 ] && [ "$1" != "--" ]; do
    folder="${1%:ro}"
    mode=""
    [ "$folder" != "$1" ] && mode=":ro"
    mounts+=(-v "$folder:$folder$mode")
    shift
done
[ "$#" -gt 0 ] && shift

exec docker run --rm -i --memory=4g --cpus=2 \
    --name "code-commandments-roslyn-run-$$-$RANDOM" --label code-commandments.roslyn=run \
    -e NUGET_PACKAGES="$packages" "${mounts[@]}" "$image" "$@"
