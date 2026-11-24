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

- **Heratio (K-9).** heratio-dev confirms whether any Heratio code reads the old forms from OpenRiC output. Requested by the OpenRiC session; Heratio's code is changed only by heratio-dev.
- **Consumers.** The drift log and the v0.62.0 changelog name the removal release, so clients have one full minor release of warning.
- **PSIS.** The archive session confirms that PSIS does not read or send these forms before v0.63.0.

## Done when

Spec v0.63.0 is released with its migration note; the service and the Workspace no longer send the three old forms, each with the test above passing in CI; the drift log records the removal.
