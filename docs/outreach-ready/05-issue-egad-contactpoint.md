---
target: https://github.com/ICA-EGAD/RiC-O/issues/new
title: Add `ContactPoint` class for ISDIAH repository contacts
labels: enhancement, openric-upstream-proposal
status: send-ready as of 2026-05-25
related-notification: workbench/B7
source-of-truth: openric-spec/docs/upstream-proposals/ContactPoint.md
---

Hello EGAD,

Filing from the OpenRiC implementation project ([openric.org](https://openric.org)). The largest single openricx term cluster after our v0.37.0 RiC-O 1.1 audit is the `ContactPoint` family — a class plus 6 address/contact properties used by every ISDIAH (International Standard for Describing Institutions with Archival Holdings) implementation we know of.

RiC-O 1.1 has rich Agent / CorporateBody / Place modelling but no first-class contact-details surface. Repository discovery (the "where do I write to / phone / visit?" question) is a basic archival API need that we currently park in `openricx:`.

### Suggested terms

```turtle
rico:ContactPoint        a owl:Class ;
    rdfs:label "Contact Point"@en .

rico:contact             a owl:ObjectProperty ;
    rdfs:domain rico:Agent ;
    rdfs:range  rico:ContactPoint .

rico:streetAddress       a owl:DatatypeProperty ; rdfs:domain rico:ContactPoint ; rdfs:range xsd:string .
rico:postalCode          a owl:DatatypeProperty ; rdfs:domain rico:ContactPoint ; rdfs:range xsd:string .
rico:city                a owl:DatatypeProperty ; rdfs:domain rico:ContactPoint ; rdfs:range xsd:string .
rico:country             a owl:DatatypeProperty ; rdfs:domain rico:ContactPoint ; rdfs:range xsd:string .
rico:telephone           a owl:DatatypeProperty ; rdfs:domain rico:ContactPoint ; rdfs:range xsd:string .
rico:email               a owl:DatatypeProperty ; rdfs:domain rico:ContactPoint ; rdfs:range xsd:string .
```

### Why a class and not direct properties on Agent

A repository typically has *multiple* contact points — postal address, visiting address, postal address for archival deposits, telephone for general enquiries, separate email for accessions, etc. Putting these as direct datatype properties on `Agent` collapses them; using a `ContactPoint` class allows multiple typed contacts:

```turtle
<.../repo/national-archives>
    a rico:CorporateBody ;
    rico:contact [
        a rico:ContactPoint ;
        rico:streetAddress "Private Bag X236" ;
        rico:city "Pretoria" ;
        rico:postalCode "0001" ;
        rico:country "South Africa" ;
        rico:telephone "+27 12 441 3200" ;
        rico:email "info@nationalarchives.gov.za"
    ] ,
    [
        a rico:ContactPoint ;
        rico:streetAddress "24 Hamilton Street" ;
        rico:city "Pretoria" ;
        rico:openingHours "Mon-Fri 08:00-15:30"
    ] .
```

### Alternatives considered (and why we still propose this)

- **schema.org/ContactPoint** — heavy, business-oriented, would cross-pollinate `schema:` into RiC graphs in a way the EGAD community has historically resisted.
- **vCard 4.0** — well-specified but XML/v-format-oriented; not a great fit for the JSON-LD / Turtle pipeline most RiC implementations use.
- **Direct datatype properties on Agent** — collapses multiple contacts into one as noted above.

A small native `rico:ContactPoint` keeps the contact surface inside the RiC namespace, avoids cross-vocabulary pulls, and is symmetrical with how `rico:Place` / `rico:Date` etc. are already modelled.

### Cross-walks

| Source | Element |
|---|---|
| ISDIAH | 5.1 Identifier of the institution; 5.1.1–5.1.5 Address |
| ISDIAH | 5.2.1 Contact details |
| EAC-CPF | `<contactDetails>` element family |
| schema.org | `schema:ContactPoint`, `schema:PostalAddress` |
| vCard 4.0 | `vcard:hasAddress`, `vcard:hasTelephone`, `vcard:hasEmail` |

### OpenRiC's interim placement

All currently in `openricx:` per [`/ns/ext/v1.html`](https://openric.org/ns/ext/v1.html):
- `openricx:ContactPoint` (class)
- `openricx:contact` (ObjectProperty)
- `openricx:streetAddress`, `openricx:city`, `openricx:postalCode`, `openricx:country`, `openricx:telephone`, `openricx:email` (DatatypeProperties)

On upstream adoption, rename to `rico:*` and retire the openricx terms.

### Optional further work

If `rico:ContactPoint` is accepted, two further fields would close the ISDIAH contact-modelling surface — but they are *deferred*, not part of this proposal:

- `openricx:openingHours` (free-form access-hours statement)
- `openricx:accessibility` (ISDIAH 5.5.5 accessibility note)

Thanks,
Johan Pieterse / OpenRiC
