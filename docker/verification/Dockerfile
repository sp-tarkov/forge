FROM mcr.microsoft.com/dotnet/sdk:10.0-alpine AS build

ENV DOTNET_NOLOGO=1 \
    DOTNET_CLI_TELEMETRY_OPTOUT=1 \
    DOTNET_SKIP_WORKLOAD_INTEGRITY_CHECK=true

COPY src/Verifier.csproj /src/Verifier.csproj

RUN dotnet restore /src/Verifier.csproj

COPY src/ /src/

RUN dotnet publish /src/Verifier.csproj -o /app --no-restore

# Runtime stage
FROM mcr.microsoft.com/dotnet/runtime:10.0-alpine

RUN adduser -D -h /home/verifier verifier

COPY --from=build /app /app

USER verifier
WORKDIR /tmp/work

ENTRYPOINT ["/app/verify"]
