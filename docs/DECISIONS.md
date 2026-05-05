# Architectural Decisions

Short, dated record of the non-obvious choices made in this POC.

## 1. Why a Media Catalog domain

Closest plausible analog to ARTE's actual back-office without modelling anything confidential: channels, programs, scheduled broadcasts, replay windows. Lets the data model exercise the parts the brief cares about (Oracle types, foreign keys, dates, full-text-ish search) without inventing a fake domain.

## 2. Eloquent + raw SQL, not one or the other

`yajra/laravel-oci8` lets Eloquent target Oracle through `oci8`. Every CRUD route uses Eloquent (relations, scopes, paginate). The `audience/per-channel` endpoint deliberately calls `DB::select()` against a **PL/SQL pipelined function** — that's the "ability to work without an ORM" line item from the brief, demonstrated with real PL/SQL, not pseudo-SQL strings.

## 3. Oracle-aware migrations

Conscious choices in `database/migrations/`:

- **`bigIncrements('id')`** under `oci8` is backed by an Oracle **sequence** (`<table>_id_seq`) and a trigger. No surprise: `Channel::find(1)` works as expected.
- **Identifier names ≤ 30 characters** for FK/index names (`fk_programs_channel`, `idx_bc_chan_sched`, …). Oracle 12.2+ allows 128, but staying under 30 keeps the schema portable to older Oracle versions still common in enterprise estates.
- **`longText('synopsis')`** maps to `CLOB`, not `VARCHAR2(4000)`. Programme synopses can exceed 4 KB.
- **`timestamp()`** maps to `TIMESTAMP(6)`. Reminder: Oracle's `DATE` type already includes seconds, so we never use `DATE` for "date only" — we'd lose the time precision.
- **Composite index on `(channel_id, scheduled_at)`** for the most common query: "what's on channel X between two dates".

## 4. Case sensitivity in search

Oracle is case-sensitive on `LIKE`. The `Program::scopeSearch` scope does `UPPER(title) LIKE UPPER(?)` to behave like users expect. This is more portable than relying on Oracle linguistic comparison settings.

## 5. Validation at the boundary, not in the model

Form Requests (`StoreBroadcastRequest`, `UpdateBroadcastRequest`) own validation. Models stay thin. Resources (`*Resource`) own the JSON shape. Each layer has one job, which is what reviewers tend to look for.

## 6. Sanctum on writes only

Reads are public; writes (`POST/PATCH/DELETE /broadcasts`) require a Sanctum token. Mirrors the realistic split where editors author content and a public schedule API serves it.

## 7. Two-track CI

- **`pest-sqlite`** runs in ~30 s, on every push. Catches regressions on logic that doesn't depend on Oracle specifics.
- **`pest-oracle`** boots a real `gvenzl/oracle-xe:21-slim-faststart` service container, installs Instant Client + `oci8`, runs the Feature suite. ~5–8 min, but it's the truth: it catches anything the SQLite path masks (sequences, identifier length, CLOB, etc.).

The migration that installs the PL/SQL package short-circuits when the connection driver isn't `oracle`, so the SQLite job stays green.

## 8. Anti-overlap broadcasts: API + DB defence in depth

A broadcast spans `[scheduled_at, scheduled_at + program.duration_min)`. Two
broadcasts on the same channel must not overlap; adjacency (`A.end == B.start`)
is allowed.

- **API layer**: a custom `BroadcastNonOverlapping` rule attached to
  `scheduled_at` in `Store/UpdateBroadcastRequest`. On update, the rule is
  short-circuited if none of `program_id / channel_id / scheduled_at` change.
  `prepareForValidation()` backfills missing fields from the existing record so
  the rule always sees a complete tuple.
- **DB layer**: an Oracle **compound trigger** (`broadcasts_no_overlap`) is the
  last line of defence. The compound shape avoids ORA-04091 "table is mutating"
  when re-querying `broadcasts` from the trigger. Failure surfaces as
  `ORA-20010`. Skipped on non-Oracle drivers so the SQLite suite stays green.

The two layers are not redundant: the API gives a clean 422 with a useful
message, the trigger protects against direct SQL writes, batch jobs, or future
endpoints that forget the rule.

## 9. Soft delete on programs, hard delete on broadcasts

`programs` carries `deleted_at` (Laravel `SoftDeletes`); `broadcasts` does not.
A program is editorial metadata that may be referenced by historical broadcasts
forever — purging it would erase airtime history. A broadcast is a scheduling
artefact: when removed it should disappear.

Rules enforced in `ProgramController::destroy`:

- **Refuse if any broadcast is in the future** (`scheduled_at >= now()`).
  Returns `422` with a clear message; the editor must reschedule or cancel
  upcoming airings before retiring the programme. This protects the live grid.
- Past broadcasts are kept untouched. `BroadcastController` eager-loads
  `program` with `withTrashed()` so historical entries still expose their
  programme metadata in `GET /broadcasts/{id}`.
- `Rule::exists('programs', 'id')->whereNull('deleted_at')` on
  `Store/UpdateBroadcastRequest` prevents scheduling a new broadcast against a
  retired programme.
- `POST /programs/{id}/restore` undoes the soft-delete (Sanctum-protected).

The deleted_at column is indexed (`idx_programs_deleted_at`) since the default
Eloquent scope filters every read by `deleted_at IS NULL`.

## 10. Maintenance via artisan: `broadcasts:purge`

A schedulable command for grid hygiene:

```
php artisan broadcasts:purge [--before=YYYY-MM-DD] [--channel=CODE] [--dry-run] [--chunk=1000]
```

- Default cutoff: `now() - 6 months`. A broadcast is "expired" when its replay
  window (or `scheduled_at + program.duration_min` when no replay is set) is
  older than the cutoff.
- `--dry-run` prints a per-channel breakdown without touching the database;
  used for capacity-planning before a real purge.
- Hard delete by design (broadcasts are scheduling artefacts, see §9). Deletes
  in `--chunk` batches via `chunkById` so a multi-year backlog doesn't blow up
  memory.
- Returns Symfony `INVALID` (exit 2) on bad `--before` or unknown `--channel`,
  not exit 1, so monitoring can distinguish "ran cleanly with nothing to do"
  from "operator typo".

The expiry SQL has an Oracle branch (`NUMTODSINTERVAL`) and a SQLite branch
(`datetime(... '+N minutes')`) so the command works on both test and prod
drivers.

## 11. Out of scope (deliberately)

- Redis, queue workers, broadcasting — not in the brief.
- A frontend — separate POC; ARTE's stack is React+TS+MUI, but this repo is the back-end half.
- Multi-tenancy, GDPR tooling — would require real ARTE specs.

## 12. What I'd do differently in production

- Move PL/SQL definitions into a versioned `db/oracle/` directory with explicit ordering, not Laravel migrations. Migrations run inside a transaction; PL/SQL DDL implicitly commits, so mixing them is brittle past trivial cases.
- Add Telescope only locally; ship logs to ARTE's existing observability instead.
- Replace the synopsis `LIKE` search with Oracle Text (`CONTAINS`) once the volume justifies it.
