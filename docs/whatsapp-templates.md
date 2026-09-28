# OpenRiC WhatsApp templates - drafts for submission

Two UTILITY templates for the shared AHG sender. Created by hand in Meta
Business Manager (the sync script `bin/wa-templates-sync.mjs` in workbench is
GET-only; nothing in code can submit a template). Johan owns that account, so
these are drafted here and submitted there.

Both are **UTILITY**, language **`en`** - not `en_ZA`. Every existing template
on this WABA is `en`, and a language mismatch fails at send time with error
`132001 template unavailable`, not at authoring time.

Naming follows the existing `<system>_<event>` convention on this WABA
(`bookings_reminder`, `callhub_sla_warning`, `lifefile_document_ready`).

---

## 1. `openric_ask_received`

**Category:** UTILITY
**Language:** en
**Fires:** when someone submits the Ask form at `openric.org/ask/`, which
POSTs to `/api/ric/v1/ask` and is handled by `AskController::ask()`.

### Body

```
New question submitted on openric.org.

Question: {{1}}

From page: {{2}}
Reply to: {{3}}
Received: {{4}}

The full text is stored against openric_question in the reference service.
```

### Parameters

| # | Content | Source | Notes |
|---|---|---|---|
| 1 | The question | `body` | Whitespace-collapsed, truncated to 300 chars |
| 2 | Originating page | `page` | Falls back to `(unknown)` |
| 3 | Reply address | `email` | Optional on the form; falls back to `(none supplied)` |
| 4 | Timestamp | server | `2026-09-28 05:40 SAST` |

### Sample

> New question submitted on openric.org.
>
> Question: How should I model a photograph album where the album and the individual prints were accessioned separately?
>
> From page: /help/the-clients/
> Reply to: archivist@example.org
> Received: 2026-09-28 05:40 SAST
>
> The full text is stored against openric_question in the reference service.

---

## 2. `openric_demand_signal`

**Category:** UTILITY
**Language:** en
**Fires:** once daily as a digest, not per event. See the design note below.

### Body

```
OpenRiC daily system report for {{1}}.

Signals recorded: {{2}}
Most frequent search: {{3}}
Questions received: {{4}}

This is an automated report for the openric.org service account.
```

### Why it is worded like this

The first draft ended "Open the stats dashboard for the full breakdown" and
opened "OpenRiC demand signals for". Both were changed before submission.

Meta classifies a template on its **wording**, not on who receives it - it has
no way to know this goes to the account owner. A daily analytics digest ending
in a call to action reads as a business newsletter, which is the MARKETING
pattern. A MARKETING classification would be fatal here: the watcher gates
MARKETING sends on a consent grant this number does not have and, by the
23 September decision, should not get; and the workbench template picker now
withholds MARKETING templates outright.

So the call to action is gone and the framing is a scheduled system report for
a service account, which is what it actually is.

### Parameters

| # | Content | Notes |
|---|---|---|
| 1 | The day | `28 September 2026` |
| 2 | Signal count | Digits, never empty - send `0` |
| 3 | Top search term | Falls back to `(none)` |
| 4 | Ask submissions that day | Digits |

### Design note - why a digest and not per-event

The demand-signal tracker records a site beacon plus search and wizard events.
Firing a WhatsApp per event would be unusable: it would flood the thread on any
day with real traffic, burn template sends, and collide with the gateway's
24-hour duplicate refusal. A daily digest is one predictable message.

If a per-event alert is genuinely wanted, it should be scoped to a narrow
trigger - a named institution identifying itself, say - and would need its own
template rather than reusing this one.

---

## Rules these drafts are built around

All four come from the workbench session, checked against the live template
cache rather than recalled.

**A parameter value may not contain a newline, a tab, or four or more
consecutive spaces.** Meta rejects the *send*, not the template, so a violation
passes authoring and fails on live traffic. The Ask form is a public textarea,
so its content *will* contain newlines. Sanitise before spooling:

```php
$clean = mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, 300);
```

**A body may not start or end with a parameter, and two parameters may not be
adjacent.** Both drafts put real words around every placeholder.

**Never pass an empty parameter.** Every optional field above has an explicit
fallback string.

**The character count is measured after substitution**, against the 1024-char
body limit. Worst case for `openric_ask_received` is roughly 720, which leaves
comfortable headroom.

**Do not reuse `ahg_staff_alert`.** It exists with three parameters and looks
generic, but its body calls the subject a Fault and promises the detail is in
CallHub. An Ask submission is neither.

## An unknown template counts as MARKETING

The watcher's `categoryOf()` caches name-to-category from Meta for 15 minutes
and **fails closed**, returning `MARKETING` for any name it does not know - the
reasoning being that the cost of guessing wrong the other way is an unconsented
marketing message.

So for up to 15 minutes after approval, or indefinitely if that Graph fetch
fails, a brand-new UTILITY template is treated as MARKETING by the sender. The
first sends may be refused for a reason that has nothing to do with this code,
and the refusal will look like a consent problem rather than a cold cache.

After approval, either wait out the 15 minutes or restart the watcher, and
treat the first send as a test rather than as traffic.

Do not assume consent enforcement is off. `WA_CONSENT_ENFORCE` defaults off,
but the env file is root-only so its current value is not readable from here.

## The spool payload for a template send

```json
{
  "to": "27...",
  "template": "openric_ask_received",
  "language": "en",
  "components": [
    { "type": "body",
      "parameters": [
        { "type": "text", "text": "<{{1}}>" },
        { "type": "text", "text": "<{{2}}>" },
        { "type": "text", "text": "<{{3}}>" },
        { "type": "text", "text": "<{{4}}>" }
      ] }
  ],
  "purpose": "transactional"
}
```

Set `purpose` explicitly - the watcher reads it. Workbench uses
`transactional` for every template send and `service` for free text. Write the
file dot-prefixed, then `rename()` it into place; the watcher skips dot-files.

## Errors worth surfacing by code

`131026` recipient not on WhatsApp · `131030` recipient not in the test allow
list · `132001` template unavailable, usually a wrong name or language ·
`190` token expired.

Test number is **27999999999**. Never a real person's number - a test once
wrote a false marketing grant into the live consent register.

## Note on the second use case

The click-to-chat links (visitor opens their own WhatsApp and messages the
business number, and share-a-record-over-WhatsApp) need **no template and no
consent record**, because the visitor initiates and that opens their own
24-hour window. They are `wa.me` links and pure client-side.

Two consequences do apply: those inbound messages land in the shared workbench
thread list, visible to every user granted WhatsApp access, so a stranger's
message to OpenRiC is not private to OpenRiC; and whoever answers needs that
grant ticked explicitly under Settings, WhatsApp access. The default is none.
