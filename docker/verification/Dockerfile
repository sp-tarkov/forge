FROM mcr.microsoft.com/dotnet/sdk:10.0-alpine AS build

ENV DOTNET_NOLOGO=1 \
    DOTNET_CLI_TELEMETRY_OPTOUT=1 \
    DOTNET_SKIP_WORKLOAD_INTEGRITY_CHECK=true

COPY verify.cs /src/verify.cs

# Publish the file-based app as a self-contained binary.
RUN dotnet publish /src/verify.cs -o /app

# Runtime stage — no SDK needed, just the compiled binary.
FROM mcr.microsoft.com/dotnet/runtime:10.0-alpine

RUN adduser -D -h /home/verifier verifier

COPY --from=build /app /app

USER verifier
WORKDIR /tmp/work

ENTRYPOINT ["/app/verify"]
