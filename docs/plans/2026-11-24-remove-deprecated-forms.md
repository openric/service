# Removing the forms deprecated in spec v0.61.0

Status: planned, 24 November 2026. Approved by Dr Johan Pieterse to be implemented after the feature freeze is lifted. Nothing here is released before then.

## What is deprecated

Spec v0.61.0 (23 November 2026, from the RiC-AG disposition table) corrected three statements that fall outside their RiC-O 1.1 domain, and deprecated the old forms. Servers send the old form beside the new one during the deprecation period, as [change control](https://openric.org/spec/change-control.html) asks.

| Old form | Why it is wrong | New form | Sent by |
|---|---|---|---|
| `rico:conditionsOfAccess` on a repository (D-1) | Domain is a Record Resource or Instantiation | `rico:isOrWasRegulatedBy` a `rico:Rule` of type access-rule, the text on `rico:generalDescription` | Service v0.18.1 (`RicSerializationService.php`, repository); Workspace v0.0.167 (`app/Api/JsonLd.php`, repository) |
| `rico:classification` on a function (D-2) | Domain is a Record Resource | `rico:hasActivityType` a `rico:ActivityType` node, the code as `rico:name` | Workspace v0.0.167 (`app/Api/JsonLd.php`, function) |
| A Mandate node on `rico:authorizingMandate` (D-3) | A text property; a node there is not a literal | `rico:authorizedBy` the `rico:Mandate` | Service v0.18.1 (`RicSerializationService.php`, agent) |

`rico:authorizingMandate` as text (ISAAR(CPF) 5.2.6, ISDIAH 5.3.3) and `rico:conditionsOfAccess` on a record or instantiation are correct and stay.

## When

Change control says a deprecated item stays "for at least one further minor release" before 1.0. The v0.61.0 migration notes say servers keep the old forms "until the next minor release". The stricter reading wins: the old forms are still sent under v0.62.0 and are removed in **v0.63.0**, the second minor release after the deprecation. Both are minor releases, so neither can ship while the freeze stands.

| Release | Specification | Servers |
|---|---|---|
| v0.62.0 | The three old forms named in each profile's deprecation notes with "removed in v0.63.0"; a SHACL Warning when an old form appears | Unchanged: both forms |
| v0.63.0 | Old forms removed from the profiles and fixtures; the Warning becomes a Violation; migration note | Old forms no longer sent |

## What servers do at v0.63.0

1. **Service.** Delete the two `ponytail:` blocks in `packages/ahg-ric/src/Services/RicSerializationService.php` (repository `rico:conditionsOfAccess`, agent node on `rico:authorizingMandate`), copy the file into `vendor/ahg/ric`, release as a conformance patch to the service, and raise its declared spec version.
2. **Workspace.** Delete the `ponytail:` blocks in `app/Api/JsonLd.php` (repository `rico:conditionsOfAccess`, function `rico:classification`). The RiC-O import (`RicReader`) keeps reading the old forms, so files exported before v0.63.0 still import.
3. **Tests that prove the new form alone passes.** Service: `IscapComplianceTest` style feature test asserting the repository and agent JSON-LD have no old form and pass the v0.63.0 shapes. Workspace: `ShaclGateTest` against the v0.63.0 fixtures, with assertions that the old forms are absent.

## Before v0.63.0

- **Heratio (K-9).** Answered on 26 November 2026 by heratio-dev (code at v1.167.11):
  - D-1: Heratio sends `rico:conditionsOfAccess` on a repository (`RicSerializationService.php:519`; on a record, line 197, is correct). Nothing reads it: the write API takes Heratio column names, not RiC-O terms, and `RdfImportService` has no reader for it. A move to `rico:isOrWasRegulatedBy` a `rico:Rule` is not planned; it is Dr Johan Pieterse's decision.
  - D-2: `rico:classification` on a function is neither sent nor read.
  - D-3: no `rico:authorizingMandate` anywhere. Heratio sends Mandate nodes on `rico:hasMandate` (not in RiC-O 1.1), and its one reader is internal: `Crm/RicToCrmMapper.php:144` maps it to `crm:P104_is_subject_to`. A move to `rico:authorizedBy` would change that mapper too; not planned, his decision.
  - Write paths: none of Heratio's RiC write or delete routes parses the three forms as RiC-O input, so none has to keep accepting them until v0.63.0. `/import` was checked through `RdfImportService`'s term map only.
  - So Heratio does not block v0.63.0; its D-1 output will fail the removal release's shapes once they reject the form.
- **Consumers.** The drift log and the v0.62.0 changelog name the removal release, so clients have one full minor release of warning.
- **PSIS.** Answered on 26 November 2026 by the session working on the PSIS plugins (atom-ahg-plugins main, v3.121.242, read from code, line numbers approximate):
  - The PHP export (`RicManageService::exportRicO`, `/ricManage/export/{id}`) sends none of D-1, D-2, D-3 or `rico:hasMandate` and reads none of them. No PSIS write path accepts them.
  - The Python Fuseki sync (`ahgRicExplorerPlugin/tools/ric_extractor_v5.py`) sends D-2, `rico:classification` on a function (about line 678). It sends D-1 on records rather than on a repository, mapped wrongly from `physical_characteristics` (about line 264). It creates `rico:Mandate` nodes linked by `rico:isOrWasRegulatedBy`, not by `rico:authorizingMandate` (about line 712). Its triplestore is read only through admin and editor routes. Moving the extractor is a PSIS batch decision for Dr Johan Pieterse; it does not block v0.63.0, because nothing outside PSIS reads that store.

## Done when

Spec v0.63.0 is released with its migration note; the service and the Workspace no longer send the three old forms, each with the test above passing in CI; the drift log records the removal.
