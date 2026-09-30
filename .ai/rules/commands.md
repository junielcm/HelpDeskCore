---
paths:
  - 'app/Console/Commands/**'
  - app/Console/Commands/TicketsCheckSlas.php
---

# Commands

## Name console command classes as valid PSR-4 classes
Do NOT name a command file `name:with-colon.php` (e.g. via `make:command tickets:check-slas`). On Windows the colon is an NTFS Alternate Data Stream, so the file is written as `tickets` and Laravel's auto-discovery (filename→class name) can't map it. Use `make:command` with a valid class name (`TicketsCheckSlas`) and set the signature with the `#[Signature('tickets:check-slas')]` attribute. Register scheduling with `Schedule::command('tickets:check-slas')->hourly()` in routes/console.php.

## SLA breach detection uses resolution + response deadlines
`tickets:check-slas` only inspects open/in_progress tickets with sla_violated=false. A ticket is breached when (a) it has no staff first_response_at and now() > created_at + response_hours, or (b) now() > created_at + resolution_hours. On breach it sets sla_violated=true, escalates priority (low→medium→high→urgent) via forceFill()->saveQuietly(), and sends a SlaBreached notification to the dept supervisors and assigned agent. default SLA thresholds are seeded in the migration so they exist under RefreshDatabase.
