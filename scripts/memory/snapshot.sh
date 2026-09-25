#!/usr/bin/env bash
# Pins a codebase for memory measurement: the committed files at one commit, archived out of the checkout without
# touching its working copy, into <out>. For a C# solution it adds what the Roslyn bridge reads beside the committed
# files: the restore output (obj/project.assets.json and the global usings), made by dotnet restore in a
# memory-capped container, never on the host, and the gitignored sources a build generates, copied from the checkout.
# Usage: scripts/memory/snapshot.sh <checkout> <commit> <out>
set -euo pipefail
checkout="$(cd "$1" && pwd -P)"
commit="$2"
out="$3"

if [ -e "$out" ]; then
    echo "$out exists; a snapshot is made once" >&2
    exit 1
fi
mkdir -p "$out"
out="$(cd "$out" && pwd -P)"
git -C "$checkout" archive "$commit" | tar -x -C "$out"
echo "$commit" > "$out/.snapshot-commit"

if ! ls "$out"/*.sln > /dev/null 2>&1; then
    exit 0
fi

# The generated sources a build writes and git ignores, which the solution compiles with.
(cd "$checkout" && git ls-files --others --ignored --exclude-standard -- '*/Generated/*.cs') | while read -r generated; do
    mkdir -p "$out/$(dirname "$generated")"
    cp -p "$checkout/$generated" "$out/$generated"
done

packages="${NUGET_PACKAGES:-$HOME/.nuget/packages}"
mkdir -p "$packages"
docker run --rm --memory=4g --cpus=2 \
    -v "$out:$out" -v "$packages:$packages" -e NUGET_PACKAGES="$packages" -e DOTNET_CLI_TELEMETRY_OPTOUT=1 \
    -w "$out" mcr.microsoft.com/dotnet/sdk:10.0 \
    sh -c 'for solution in *.sln; do dotnet restore "$solution" -p:RestoreUseStaticGraphEvaluation=false; done && dotnet build *.sln -t:GenerateGlobalUsings -p:DesignTimeBuild=true -nologo -v:q'
