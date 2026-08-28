# Near-realtime OCR processing — analysis & implementation plan

Status: **Proposal / RFC** · Companion doc also published to
[R0Wi-DEV/workflow_ocr_backend](https://github.com/R0Wi-DEV/workflow_ocr_backend/blob/claude/ocr-realtime-processing-plan-490z1p/doc/near-realtime-processing-plan.md)
(same content — this plan spans both repos).

## 1. Goal

Today, whenever a Workflow Engine rule matches a file event, `workflow_ocr` only ever
writes a row into Nextcloud's `oc_jobs` table (`IJobList::add(ProcessFileJob::class, …)`).
The file is only actually OCR'd the next time Nextcloud's cron runs `ProcessFileJob`,
which — depending on AJAX/Webcron/System cron configuration — can take anywhere from
~1 to 15+ minutes.

We want an **optional, "near real-time" path** that starts processing (almost)
immediately when the [`workflow_ocr_backend`](https://github.com/R0Wi-DEV/workflow_ocr_backend)
ExApp is installed and supports it, while staying **100% backwards compatible**:

- `workflow_ocr` **must keep working unmodified** when no backend / an old backend is
  installed (pure local `ocrmypdf`, or Remote backend without push support) — cron is
  and stays the source of truth.
- An **old `workflow_ocr`** talking to a **new `workflow_ocr_backend`** must keep working
  exactly as before (the backend simply never receives a push and nobody calls the new
  route).
- A **new `workflow_ocr`** talking to an **old `workflow_ocr_backend`** must transparently
  fall back to the existing cron-based flow (no user-visible error, no lost jobs).

## 2. How context_chat actually does it (research findings)

Two different things are easy to conflate here — the analysis found **both** in the
`context_chat` / AppAPI ecosystem, but only one of them is the "Python lib waiting for
an event" mechanism referred to in the feature request:

1. **`FileSystemListenerJob`** (`nextcloud/context_chat`, PHP side) — a normal
   `TimedJob` that periodically flushes a DB-backed queue of file-system events into
   the `context_chat_backend`. This is architecturally the *same pattern
   `workflow_ocr` already uses* (PHP event → queue table → cron flush) — just with a
   tighter internal queue. It is **not** the mechanism we want to imitate.

2. **AppAPI "Events Listener" + `nc_py_api`** (the actual "Python lib" mechanism) —
   this is what's relevant:
   - An ExApp registers itself, once (typically in its `enabled_handler`), via the OCS
     endpoint `POST /apps/app_api/api/v1/events_listener` with
     `{"eventType": "node_event", "actionHandler": "/some/route", "eventSubtypes": [...]}`.
   - Nextcloud core then, whenever a matching event fires (`NodeCreatedEvent`,
     `NodeWrittenEvent`, …), performs an **async, non-blocking HTTP POST** to the
     ExApp's `actionHandler` route — signed with the same AppAPI ExApp auth that
     `nc_py_api`'s `AppAPIAuthMiddleware` already verifies on the backend today.
   - Because the ExApp is a long-running FastAPI/uvicorn process (deployed as a
     Docker container by AppAPI/HaRP, exactly like `workflow_ocr_backend` already is),
     it is **always "listening"** on that route — there is no polling on the Python
     side, `nc_py_api` just gives you decorators/`set_handlers()` to receive the push.
   - **Important caveat found during research**: this generic events bridge only
     relays a fixed, whitelisted set of *core* Nextcloud events (file node events;
     system tag support is still tracked as an [open feature request,
     nextcloud/app_api#281](https://github.com/nextcloud/app_api/issues/281)). It does
     **not** relay arbitrary third-party-app-defined PHP events. That matters for us
     because all of `workflow_ocr`'s actual business logic — which Flow rule matched,
     mimetype/tag conditions, "is this the OCR-rewrite itself" guard, etc. — lives in
     `Operation::onEvent()` / `IRuleMatcher`, not in a generic core event. Re-deriving
     that logic in Python from raw `NodeCreatedEvent`/`NodeWrittenEvent` pushes would
     mean **duplicating the Workflow Engine's condition matching in two languages** —
     fragile and a maintenance trap.

**Conclusion**: we adopt the same *shape* of mechanism (Nextcloud pushes a small
message to an always-listening ExApp instead of a cron table being polled) but target
it directly at `workflow_ocr_backend`'s own route instead of going through AppAPI's
generic/whitelisted core-events bridge. Conveniently, `workflow_ocr` **already has all
the plumbing for this** — see §3.

## 3. What already exists in the two repos (relevant building blocks)

- `workflow_ocr_backend` is already an AppAPI ExApp (`appinfo/info.xml` +
  `external-app`/`docker-install`), already depends on `nc-py-api[app]`, already uses
  `AppAPIAuthMiddleware` + `set_handlers()` in `workflow_ocr_backend/app.py`, and
  already imports `AsyncNextcloudApp`/`NextcloudApp` for its `enabled_handler`. It
  exposes exactly two routes today: `POST /process_ocr` (upload bytes → OCR'd bytes,
  synchronous) and `GET /installed_languages`.
- This repo's `Operation::onEvent()` (`lib/Operation.php`) is invoked **synchronously
  by the Workflow Engine**, already does all condition matching, and today only does
  `$this->jobList->add(ProcessFileJob::class, $argsArray)` with
  `$argsArray = ['uid' => ..., 'fileId' => ..., 'settings' => ...]`.
- `lib/BackgroundJobs/ProcessFileJob.php` (a `QueuedJob`, run by cron) simply calls
  `IOcrService::runOcrProcessWithJobArgument($argument)` — all Nextcloud file
  read/write/lock/notify logic for OCR lives in `OcrService.php`, not in the backend.
- `lib/Service/IOcrBackendInfoService.php` / `OcrBackendInfoService.php` **already**
  knows, at PHP request time and without an extra network round-trip, whether a Remote
  backend is installed & enabled (`isRemoteBackend()`, via
  `IAppApiWrapper::getExApp(Application::APP_BACKEND_NAME)`).
- `lib/Wrapper/AppApiWrapper.php` **already wraps**
  `OCA\AppAPI\PublicFunctions::exAppRequest($appId, $route, $userId, $method, $params, $options, $request)`
  — i.e. this app can *already* make an AppAPI-signed HTTP call to an arbitrary route
  on the `workflow_ocr_backend` ExApp. This is the exact same signed-transport AppAPI
  itself uses to deliver its `events_listener` payloads — we don't need to invent
  anything new here, just call it with a new route name.
- `lib/OcrProcessors/Remote/WorkflowOcrRemoteProcessor.php` today uploads the full
  file to `/process_ocr` and blocks for the OCR result — this stays **completely
  unchanged**; it's what `ProcessFileJob`/cron still uses as the reliable fallback
  path and is not on the near-real-time hot path (see §4).

This means the "listen for frontend events" building block the feature request asks
for does not need a new protocol — it needs one new backend route plus one new
frontend listener wired through code that already exists.

## 4. Proposed design

### 4.1 Overview

```
File event matches a Flow rule
        │
        ▼
Operation::onEvent()                         (unchanged: condition matching)
        │
        ├─ 1. IJobList::add(ProcessFileJob, args)   ← ALWAYS, unconditionally
        │        (durable, at-least-once fallback — unchanged from today)
        │
        └─ 2. dispatch OcrJobEnqueuedEvent(args)     ← NEW
                        │
                        ▼
        NearRealtimeDispatchListener (NEW, lib/Listener)
                        │  only if isRemoteBackend() AND backend version
                        │  advertises push support (cheap, no network call —
                        │  read from the already-cached AppAPI app-info)
                        ▼
        IAppApiWrapper::exAppRequest('workflow_ocr_backend',
            '/notify_job', uid, 'POST', {fileId, settings},
            ['timeout' => 2-3s])                      ← best-effort, fire-and-forget,
                        │                                 any failure is caught & logged
                        ▼                                 at debug level, nothing else
                                                           happens (cron still has it)
   ── network boundary, AppAPI-signed ──
                        ▼
   workflow_ocr_backend: POST /notify_job              (NEW FastAPI route, behind the
        │  responds 202 immediately, hands off          existing AppAPIAuthMiddleware)
        │  to a FastAPI BackgroundTask
        ▼
   BackgroundTask calls back into Nextcloud OCS,
   as the ExApp, using nc_py_api's NextcloudApp.ocs()   ← reuses the *existing*
   against a NEW OCS route:                                nc_py_api dependency,
   POST /ocs/v2.php/apps/workflow_ocr/                     no new Python file-I/O
        api/v1/jobs/process  {uid, fileId, settings}       capability needed
                        │
                        ▼
   NEW OCS controller in workflow_ocr (PHP, THIS repo)
        │  runs IOcrService::runOcrProcessWithJobArgument($args)
        │  — the EXACT SAME call ProcessFileJob::run() makes today
        │  on success: IJobList::remove(ProcessFileJob::class, $args)
        │       so cron does not redundantly reprocess the file
        ▼
   File is OCR'd — usually within ~1-2s of the triggering event
```

Two round trips (frontend→backend "wake", backend→frontend "execute now") rather than
having the backend do the OCR itself and push bytes around. This is a deliberate
choice, not an oversight — see §4.4 for why.

### 4.2 Why keep `IJobList::add()` unconditional (dual-write, not either/or)

The feature request phrases this as "instead of writing to the cron task table" — in
the **steady state** that's exactly what happens: the job spends at most a second or
two in `oc_jobs` before the OCS callback removes it again, so cron is never the thing
that actually processes the file. But we deliberately do **not** make the
`IJobList::add()` call conditional on the push succeeding, because:

- The push is 3 independent, best-effort hops (PHP→ExApp wake, ExApp→PHP OCS call,
  PHP OCR execution) each of which can fail transiently (backend restarting, HaRP
  hiccup, temporary Nextcloud OCS auth issue). Making the durable job conditional on
  all three succeeding would turn "near-realtime is a nice speed-up" into "near-realtime
  can silently drop OCR jobs", which is an unacceptable regression versus today's
  guarantees.
- `IJobList::add()` is cheap (a single DB insert) and NC already treats `oc_jobs` as a
  queue that's safe to over-populate; removing it in the success case keeps the table
  clean.
- This gives us "at-least-once, usually processed in ~1-2s" without any new
  distributed-consensus problem to solve.

A future, more aggressive "strict fast path" (skip `IJobList::add()` entirely when
push is known-supported, trading a small at-most-once risk for zero `oc_jobs` writes)
is noted in §7 as a possible opt-in follow-up, not part of this plan.

### 4.3 Idempotency / double-processing guard

Because `IJobList::add()` always happens, there's a race: `ProcessFileJob` could fire
from cron before the async push round-trip completes and removes it. This already has
to be handled defensively:

- Primary guard: the OCS callback removes the matching `ProcessFileJob` entry via
  `IJobList::remove(ProcessFileJob::class, $args)` as soon as it *starts* (or
  completes — TBD during implementation, see open question in §6) processing, which
  closes the window to effectively zero in the common case.
- Defense in depth: `OcrService`/`Operation.php` already has a "was this event
  triggered by our own OCR rewrite" guard
  (`eventTriggeredByOcrProcess`/`IProcessingFileAccessor`). The same pattern (a
  short-lived "currently processing this file id" marker, e.g. via NC's
  `ILockingProvider` or an in-memory/`ICache`-backed guard keyed by `fileId`) should be
  extended to also cover "concurrent OCR of the same file via two trigger paths", so
  that even in the rare race window, the second runner no-ops instead of double-OCRing
  the file.

### 4.4 Why the backend stays a "dumb" stateless OCR engine

An alternative design would have the backend, on receiving the wake call, pull the
file itself via `nc_py_api`'s Files/WebDAV API, run OCR, and write the result back —
mirroring how some ExApps operate on Nextcloud content directly. We recommend
**against** this for `workflow_ocr_backend`, at least for v1:

- All Nextcloud-side concerns for an OCR write-back — file locking, ETags,
  notifications, "don't re-trigger yourself" guards, recognized-text sidecar handling,
  permission checks — already exist and are tested in `OcrService.php`. Re-implementing
  (a subset of) that in Python would duplicate business logic across languages, same
  problem we're avoiding in §2 by not reusing AppAPI's generic node-event bridge.
  The two-hop design keeps **100% of Nextcloud file handling in PHP**, and the backend's
  job is unchanged in kind — it already only ever deals with bytes in / bytes out.
- The backend's dependency footprint doesn't grow (no new Files API usage to secure /
  test / keep in sync with Nextcloud file-locking semantics).
- It keeps the change minimally invasive on both sides — a small, auditable diff
  rather than a re-architecture.

### 4.5 Backwards compatibility & capability negotiation

- **Old backend, new frontend**: `POST /notify_job` doesn't exist on the old backend →
  `exAppRequest()` fails/returns non-2xx → caught, logged at debug level, function
  returns normally. `IJobList::add()` already happened, cron processes it exactly as
  today. To avoid attempting (and logging noise from) a doomed call on *every single
  matching event* once we already know the backend doesn't support it, gate the
  attempt behind a **version check** first (see below) and add a short-TTL negative
  cache (`ICache`, a few minutes) for the rare case a version check says "should work"
  but the call still fails.
- **New backend, old frontend**: old frontend never calls `/notify_job` and never
  registers the new OCS route consumer; backend's new route just sits unused. No
  behavior change.
- **Version gating (no extra network round trip)**: `IOcrBackendInfoService` (via
  `IAppApiWrapper::getExApp()`) already fetches the AppAPI app-info for
  `workflow_ocr_backend`, which includes its installed `<version>`. Add a semver
  comparison against a `MIN_BACKEND_VERSION_FOR_PUSH` constant in
  `Application.php` (bump whenever the backend's push contract changes) — this is a
  pure, already-cached, in-memory comparison, so "does the backend support push" costs
  nothing extra per event.
- **AppAPI not installed at all**: `isRemoteBackend()` already returns `false` today
  → the new dispatch listener never fires → purely local/cron path, unchanged.
- **Operator escape hatch**: a new Global Setting, "Enable near-real-time processing
  (experimental)" (via the existing `GlobalSettingsService`/`IGlobalSettingsService`),
  defaulting to **off** for the first release so this ships as opt-in, then flips to
  default-on once it's proven in the wild.

## 5. Concrete work items

### 5.1 `workflow_ocr` (this repo, PHP + JS)

1. `lib/Events/OcrJobEnqueuedEvent.php` (new, follows the existing `lib/Events`
   convention): small typed event carrying the same `$argsArray` shape
   (`uid`, `fileId`, `settings`) already built in `Operation.php`.
2. `lib/Operation.php`: after the existing `$this->jobList->add(...)` call, dispatch
   `OcrJobEnqueuedEvent` via `IEventDispatcher::dispatchTyped()`. This is the only
   change to `Operation.php` — all condition-matching logic stays untouched.
3. `lib/Listener/NearRealtimeDispatchListener.php` (new, follows the existing
   `lib/Listener` convention): subscribes to `OcrJobEnqueuedEvent`; short-circuits
   unless `GlobalSettingsService` has the feature enabled AND
   `IOcrBackendInfoService::isRemoteBackend()` AND the version gate (§4.5) passes;
   otherwise calls `IAppApiWrapper::exAppRequest('workflow_ocr_backend', '/notify_job',
   $uid, 'POST', [...], ['timeout' => 3])` inside a try/catch that only logs at
   `debug` level — this call must be provably incapable of throwing back into
   `Operation::onEvent()`/the triggering file-event request.
4. `IOcrBackendInfoService`/`OcrBackendInfoService`: extend with
   `supportsNearRealtimeDispatch(): bool` implementing the version-gate + short-TTL
   negative cache from §4.5, so the listener stays a thin caller.
5. New OCS controller (e.g. `lib/Controller/JobsController.php`,
   `POST /ocs/v2.php/apps/workflow_ocr/api/v1/jobs/process`): AppAPI/ExApp-authenticated
   (same pattern AppAPI uses for other ExApp→Nextcloud callbacks), runs
   `IOcrService::runOcrProcessWithJobArgument($args)` synchronously in that request,
   then `IJobList::remove(ProcessFileJob::class, $args)` on success (§4.3). Register the
   route in `appinfo/routes.php`.
6. Idempotency guard extension per §4.3 (small, shared between `ProcessFileJob::run()`
   and the new controller so both entry points funnel through the same "already being
   processed?" check before calling `OcrService`).
7. `Application.php`: add `MIN_BACKEND_VERSION_FOR_PUSH` constant.
8. Admin settings (Vue, `src/`): checkbox for the new Global Setting, plus a small
   status line ("backend does/doesn't support near-real-time processing — upgrade to
   ≥ X.Y.Z") sourced from `IOcrBackendInfoService`, so operators get instant feedback
   instead of silently always falling back to cron.
9. `lib/SetupChecks`: optional new setup check warning if the setting is on but the
   installed backend is below the minimum version, or AppAPI isn't reachable — nudges
   operators rather than failing silently.
10. Tests: PHPUnit unit tests for the new event/listener (mock `IAppApiWrapper`,
    assert the call happens only when all three gates pass, assert exceptions from
    `exAppRequest` never propagate), for the version-gate cache, and for the new
    controller (mock `IOcrService`, assert `IJobList::remove` is called on success and
    *not* called on failure). Extend the integration test suite
    (`phpunit.integration.xml`) with an end-to-end case using a stub HTTP server in
    place of the backend, covering both the "backend supports push" and "backend
    doesn't support push" cases to prove the fallback path is exercised, not just
    unit-mocked.

### 5.2 `workflow_ocr_backend` (companion repo, Python)

See the companion doc in that repo
(`doc/near-realtime-processing-plan.md` on the same branch) for the full breakdown —
summary: a new `POST /notify_job` FastAPI route behind the existing
`AppAPIAuthMiddleware` that responds `202` immediately and hands off to a
`BackgroundTask`, which calls back into this app's new OCS controller
(`POST /ocs/v2.php/apps/workflow_ocr/api/v1/jobs/process`) via `nc_py_api`'s
`NextcloudApp.ocs()`, plus an `<info.xml>` version bump and test coverage.

## 6. Open questions to resolve during a short implementation spike

- **Auth shape of the backend→Nextcloud OCS callback**: confirm that
  `NextcloudApp.ocs()`/`AsyncNextcloudApp.ocs()` can target a third-party app's own OCS
  route (not just `app_api`'s) with correct user impersonation (`uid`) for permission
  checks on `OcrService`'s file access — this is standard ExApp-to-Nextcloud calling
  convention, but should be validated against a real AppAPI-enabled Nextcloud instance
  before writing the final controller.
- **Remove-before vs remove-after**: whether `IJobList::remove()` should fire before
  starting OCR (minimizing the double-run window but risking "job vanished" if the OCS
  request itself dies mid-flight) or after successful completion (safer, slightly wider
  race window that the idempotency guard in §4.3 must still cover regardless).
- **Exact wording/route names** (`/notify_job`, `/jobs/process`) are placeholders for
  this proposal and should be finalized against the project's existing naming/OpenAPI
  conventions (note this repo already generates its remote client from
  `openapi-spec.json` — the new OCS route should get the same treatment for
  consistency, and the backend's FastAPI app already auto-generates OpenAPI docs).

## 7. Explicitly out of scope for this plan

- Adopting AppAPI's generic `events_listener` core-event bridge directly (see §2) —
  revisit only if/when it grows support for custom app-defined events, at which point
  it could *replace* the bespoke wake call, not the OCS-callback/idempotency design.
- Having the backend fetch/write files itself via `nc_py_api` Files API (§4.4) — worth
  reconsidering later if the two-hop OCS callback proves to be a bottleneck, but not
  needed to hit "near real-time" (~1-2s) latency.
- A "strict fast path" that skips `IJobList::add()` entirely (§4.2) — possible future
  opt-in, not needed to satisfy the stated backwards-compatibility requirements.
- Any change to the `Local` (in-process `ocrmypdf`) processing path — the "near
  real-time" ask in the feature request is specifically about the Remote backend case
  (not being dependent on `ocrmypdf` being installed alongside Nextcloud).
