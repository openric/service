# 2026-09-28 - OpenRiC WhatsApp: click-to-chat, share links, and the Ask notification

Commits `46ec077` (openric/spec), `7c5ffaa` and `218db19` (openric/service).

OpenRiC becomes the sixth system on the estate's shared WhatsApp sender. Three
surfaces, and only one of them needed the gateway at all.

## The useful finding: two of the three needed no backend

The request was "add WhatsApp to OpenRiC", which sounds like an integration.
Two thirds of it turned out to be links.

- **Visitor messages us.** A `wa.me/<sender>?text=...` link on `/ask/`. The
  visitor initiates, which opens *their own* 24-hour window, so no template, no
  consent record and no send path is involved. Replies happen from the existing
  workbench WhatsApp view.
- **Share a record.** A share action on each Browse row. Critically this URL
  carries **no number** - `wa.me/?text=...` opens the visitor's WhatsApp and
  lets them pick a recipient. Putting the sender number there would silently
  convert "share this record with a colleague" into "message OpenRiC", landing
  strangers' shares in the maintainer's thread list.

Only the **Ask-form notification to the maintainer** needed the gateway, because
it fires at any hour and cannot assume an open window.

The sender (`27648830533`) was verified against two independent codebases -
`callhub/config/services.php` and `workbench/api/src/services/whatsappSend.ts` -
rather than taken from one default.

## What was built

`AskController` already stored each question to `openric_question` then
best-effort emailed it. A sibling channel now queues a WhatsApp notification
with the same contract: the stored row is the durable record and neither channel
can fail the request. It is deliberately **not** conditional on the email
succeeding - a mail outage is exactly when a second channel earns its keep.

`AhgRic\Services\WhatsAppNotifier` writes a dot-prefixed JSON payload into
`/var/spool/ahg-whatsapp` and renames it into place. OpenRiC holds no Meta
credential; the root `ahg-whatsapp-watcher` does the sending. Off unless
`OPENRIC_WHATSAPP=true` and `OPENRIC_WHATSAPP_TO` are set.

It is much smaller than CallHub's equivalent on purpose - one recipient, one
template, so no recipient model, no per-type template map, no number
normalisation beyond a digit strip. Grow it at the second caller.

## Traps, each of which would have failed in production

**Template parameters cannot contain a newline, a tab, or four or more
consecutive spaces.** Meta rejects the **send**, not the template, so a
violation passes authoring and fails on live traffic. The Ask form is a public
textarea, so unsanitised input *will* contain newlines. Six tests cover this
against deliberately hostile input.

**The Meta create form now defaults "Type of variable" to `Name`.** A
named-variable template produces `{{question}}` placeholders and needs
`parameter_name` on every send. The watcher emits positional
`{"type":"text","text":...}` and all fourteen existing templates are positional.
A named template would pass approval and then fail every single send. Switched
to `Number` before submitting; the test asserts no `parameter_name` is emitted.

**An unknown template counts as MARKETING.** The watcher's `categoryOf()` caches
name-to-category for 15 minutes and fails closed to `MARKETING`. For up to 15
minutes after approval a brand-new UTILITY template is treated as marketing,
which consent enforcement gates - so the first send may be refused for a reason
that looks like a consent problem rather than a cold cache.

**Default message validity is 10 minutes.** Undelivered messages are dropped.
Fine for a time-critical alert, wrong for this: a phone off for a quarter of an
hour means the notification silently vanishes.

**`packages/` is not what runs.** The path repository uses `"symlink": false`,
so composer *copies* packages into `vendor/`, and `AhgRic\` autoloads from
`vendor/ahg/ric/src`. Editing `packages/ahg-ric/` has no runtime effect until
`composer install` re-mirrors. `composer dump-autoload` is not enough, and fails
for a non-`www-data` user because the post-dump `artisan package:discover` needs
`bootstrap/cache`, whose ACL grants `user:www-data:rwx` while `group::` is
`r-x` - group membership does not help.

## The decision that changed on evidence

The second template - a daily demand-signal digest - was drafted, then held
back.

Workbench supplied all fourteen approved templates. Every one names its own
system or domain object, so **nothing generic exists to reuse**; the only
internal staff template in the estate is `ahg_staff_alert`, whose body calls the
subject a Fault and promises the detail is in CallHub. That is a genuine estate
gap, not merely an OpenRiC one.

More decisive: `lifefile_review_reminder` came back **MARKETING** for wording no
more promotional than "your details were last reviewed X ago and are now due for
review". If a plain reminder classifies as marketing, a daily analytics digest
with a call to action almost certainly would - and a MARKETING classification
makes it unusable, since the watcher gates those on consent this number does not
have and the workbench picker now withholds them outright.

The call-to-action line was removed and the framing changed to a scheduled
system report, which lowers but does not eliminate the risk. Three options are
open: the workbench notification bell (no template, no classifier risk, and the
surface the standing rules already nominate for this), a generic estate-level
`ahg_system_notice`, or the OpenRiC-specific template as reworded. Undecided.

Only `openric_ask_received` has been submitted.

## State

Live: both `wa.me` link surfaces on openric.org.

Submitted, awaiting Meta: `openric_ask_received`, UTILITY, `en`, positional,
four parameters, no header, footer or buttons.

Not live: the Ask notification. It needs `composer install` as `www-data` to
mirror into `vendor/`, then `OPENRIC_WHATSAPP=true` and `OPENRIC_WHATSAPP_TO`
in `.env` - and neither should happen until the template is approved and the
watcher's category cache has warmed.

Template drafts, with the full rule set and the spool payload shape, are in
`docs/whatsapp-templates.md`.
