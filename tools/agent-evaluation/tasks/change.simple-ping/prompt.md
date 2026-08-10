# Task

Add a dependency-free `GET /ping` endpoint to this PHPThis application.

It must return status `200`, exactly these headers:

```text
Content-Type: application/json; charset=utf-8
Cache-Control: no-store
```

Its body must be exactly this JSON followed by one LF byte:

```json
{"status":"pong"}
```

Keep the existing `GET /health` behavior unchanged. Follow the installed PHPThis and application guidance, add automated behavior evidence, and run the complete application validity gate before reporting completion.
