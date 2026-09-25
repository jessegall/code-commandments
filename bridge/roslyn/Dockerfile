# The Roslyn bridge as the image every run of it uses: .NET never runs on the host. Built once per version of the
# bridge's sources, under the tag bridge/roslyn/IMAGE names, and published with each release:
#
#   docker build -t "$(cat bridge/roslyn/IMAGE)" bridge/roslyn
#
# The build uses one MSBuild worker and tells .NET it has two processors, so it stays within two cores.
FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
ENV DOTNET_CLI_TELEMETRY_OPTOUT=1 DOTNET_NOLOGO=1 DOTNET_PROCESSOR_COUNT=2
WORKDIR /src
COPY *.cs Roslyn.Bridge.csproj ./
RUN dotnet publish Roslyn.Bridge.csproj -c Release -o /bridge -maxcpucount:1

# The runtime, the published bridge, and the SDK's reference packs: a project that was never restored, and a file
# outside every project, still compile against the frameworks they name.
FROM mcr.microsoft.com/dotnet/runtime:10.0
ENV DOTNET_CLI_TELEMETRY_OPTOUT=1 DOTNET_NOLOGO=1 DOTNET_PROCESSOR_COUNT=2
COPY --from=build /usr/share/dotnet/packs /usr/share/dotnet/packs
COPY --from=build /bridge /bridge
ENTRYPOINT ["dotnet", "/bridge/roslyn-bridge.dll"]
