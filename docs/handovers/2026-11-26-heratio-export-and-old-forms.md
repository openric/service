# Handover to heratio-dev and archive: export findings and the old forms (26 November 2026)

Status: sent to heratio-dev on 26 November 2026, at Dr Johan Pieterse's request, from the OpenRiC session. No archive session was running, so the PSIS questions went to the Video series creation session, which works on the PSIS plugins, also at his request. Nothing here changes Heratio or PSIS. heratio-dev owns Heratio's code, and archive owns PSIS. Any production release stays with Dr Johan Pieterse's approval.

Read on heratio-dev at `fe929b902` (v1.167.11), `packages/ahg-ric`, on 26 November 2026. Every finding below comes from reading code. None has been reproduced on a running Heratio or PSIS site.

## 1. The record-set export (daily plan objective 2)

### 1a. The export leaves out a record's restrictions (security; confirm first)

- `LinkedDataApiController.php:242` adds the ISCAP step (`addIscapCompliance`: security classification, access restrictions, personal-data flag) to a single record. Line 147 does the same for an agent.
- `exportRecordSet` (line 262, which calls the serializer at line 288) does not. Neither does `RicSerializationService::exportRecordSet` (line 913).
- **Risk:** if confirmed, a client that exports a record set can't tell that some of its records are classified or restricted.
- **The OpenRiC fix (service v0.18.5, commit `b8cd045`):**
  - `exportRecordSet` runs `addIscapCompliance` on the record set and on each descendant inside `getAllDescendants`;
  - the security-classification query got a `Schema::hasTable` guard, like the other two lookups;
  - the test is `IscapComplianceTest::test_a_record_sets_export_carries_them_on_the_root_and_its_descendants`.
- **To confirm on dev:** pick a published record set whose record, or one of whose descendants, has a classification or an `icip_access_restriction` row. Compare `/api/ric/v1/records/{slug}` with `/api/ric/v1/records/{slug}/export`. If the export lacks `rico:isOrWasRegulatedBy`, the finding stands.

### 1b. The descendants are looked up by slug, not id (defect; openric/service#19)

- `getAllDescendants` (line 1132) reads each child's id back from its `@id` (`extractIdFromUri`). That `@id` ends in the slug, so every child comes out as "Information Object not found". A slug that starts with digits gives another record instead. Heratio fixed this in v1.167.12; advisory GHSA-2q8c-5f88-vghq, published 27 November 2026.
- The same file has the converter defect from #19: Turtle and RDF/XML drop nested nodes and datatypes.
- **The OpenRiC fix (service v0.18.3, commit `0708699`):**
  - take the child ids from `information_object.parent_id`, ordered by `lft`, and skip error nodes;
  - port `JsonLdConverter` from the same commit;
  - tests: `ExportTreeAndRootRepositoryTest`, and `JsonLdConverterTest`, which compares against rdflib.
- **To confirm on dev:** export a record set that has children. If the `@graph` holds error nodes, or no descendants, the finding stands.

### 1c. Fixed in OpenRiC since, not checked in Heratio (look while you are in the file)

- **v0.18.6:** the `/ric-api` graph viewer routes had no visibility filter.
- **v0.18.7:** the `/admin/ric` pages required no sign-in inside the app. Heratio may already sign these in through its own middleware; please say.
- **v0.18.8:** `/id/{kind}/{id}` sent browsers to pages that answer 404, and redirected a draft to a URL naming its slug.

Each is in OpenRiC's `docs/drift-log.md` with its direction, `openric→heratio`.

**PSIS (archive):** say whether the PSIS RiC export (`/ricManage/export/{id}`) carries the record's classification and restrictions, and whether any of 1a to 1c applies to it.

## 2. The old forms before their removal (daily plan objective 3, K-9)

Spec v0.61.0 deprecated three forms, which are removed in v0.63.0 after the freeze. OpenRiC's `docs/plans/2026-11-24-remove-deprecated-forms.md` has the plan. Before then we need to know whether Heratio or PSIS **sends** or **reads** them.

| Form | Heratio sends it? (from code) | Question for heratio-dev | Question for archive |
|---|---|---|---|
| `rico:conditionsOfAccess` on a repository (D-1) | Yes: `RicSerializationService.php:519`. Line 197, on a record, is correct and stays | Does any Heratio code read it from OpenRiC output? Will you move the repository to `rico:isOrWasRegulatedBy` a `rico:Rule` of type access-rule, with the text in `rico:generalDescription`? | Does PSIS send or read it? |
| `rico:classification` on a function (D-2) | Not found in `RicSerializationService.php` | Does any Heratio code send or read it? | Does PSIS send or read it? |
| A Mandate node on `rico:authorizingMandate` (D-3) | Heratio sends a Mandate node on `rico:hasMandate` (line 391, built at 1193 and 1200). That property is not in RiC-O 1.1, so it is a separate question from D-3 | Does any code read `rico:authorizingMandate` as a node? Will `rico:hasMandate` move to `rico:authorizedBy`? | Does PSIS send or read either? |

: The three deprecated forms and the open questions

**Also wanted from heratio-dev:** the list of Heratio's RiC write and delete routes, `packages/ahg-ric/routes/api.php` from line 168. Any write path that accepts the old forms must keep accepting them until v0.63.0.

**My own search was incomplete.** A search of all of heratio-dev's packages for readers of these forms timed out. So "no reader found" isn't established, and that's the question.

## What comes back to OpenRiC

For each of 1a, 1b and 1c: confirmed and fixed (the commit, the tests and any help change), or refuted (the evidence). For section 2: an answer per form from each of heratio-dev and archive. OpenRiC records the answers in the drift log and in the removal plan's "Before v0.63.0" section.
