{{-- Overrides Scribe's default "Authenticating requests" section. The Forge API is open and unauthenticated (stated --}}
{{-- in the Introduction), so this slot, which renders immediately after the Introduction, is repurposed to document --}}
{{-- the Cloudflare edge rate limits instead. The $isAuthed / $authDescription / $extraAuthInfo variables Scribe --}}
{{-- passes in are intentionally unused. --}}
@verbatim
# Rate Limits

The Forge API is rate limited at Cloudflare's edge, applied per client IP across all `/api/v0/` endpoints. There are two windows:

| Window | Limit | Block duration |
|--------|-------|----------------|
| Burst | 40 requests / 10 seconds | 30 seconds |
| Sustained | 200 requests / 60 seconds | 60 seconds |

When either limit is exceeded, the API responds with HTTP `429 Too Many Requests`. The response carries a `Retry-After` header (the number of seconds to wait before retrying) and this example JSON body.

```json
{
    "success": false,
    "code": "RATE_LIMITED",
    "message": "Too many requests. Retry after the number of seconds in the Retry-After header."
}
```

Honour the `Retry-After` header and back-off before retrying. Program against the `429` status and the `Retry-After` header rather than the specific numbers above, which may be tuned over time for any reason.

Attempting to sidestep or evade these limits will be seen as an act of hostility. If you require a higher rate limit then what is provided above please contact us on Discord to discuss specifics.
@endverbatim
