---
status: draft
to: Matthew Damigos (DLIB, Ionian University)
re: RiC-CM NavTool — permission to reference + license request
last-updated: 2026-04-20
---

# Draft email — Matthew Damigos (RiC-CM NavTool)

**Recipient:** Matthew Damigos, Laboratory of Digital Libraries and Electronic Publishing, Ionian University
**Contact page:** https://ilam.ionio.gr/en/staff/764-damigos/
**Project referenced:** https://github.com/DLIB-Ionian-University/ric-cm-nav / https://dlib-ionian-university.github.io/ric-cm-nav/

**Subject:** RiC-CM NavTool — permission to reference + a licensing question

---

Dear Dr. Damigos,

I hope this finds you well. I'm writing about your **RiC-CM NavTool** (`dlib-ionian-university.github.io/ric-cm-nav`), which is the cleanest RiC-CM 1.0 browser I've come across — thank you for building and sharing it publicly.

I'm working on **OpenRiC**, an open-source records-in-contexts platform (Laravel + Fuseki + Elasticsearch, RiC-O backed) aimed at archives that need a working implementation of the model rather than just reference documentation. As we build out the UI, your NavTool is exactly the kind of conceptual reference our users need alongside their actual holdings.

Two requests, if you'd be open to them:

1. **May we embed or link to the hosted NavTool** from within OpenRiC — for example, a "Model Reference" button next to each RiC entity picker that opens your tool? This is zero-cost to you and credits you prominently in our UI and docs. If you'd prefer we only link (vs. iframe), or not at all, I'd respect that.

2. **Would you consider adding a LICENSE file to the repo** (MIT, Apache-2.0, or CC-BY-4.0 would all work for us)? Right now the repo has no license, which in GitHub terms means all-rights-reserved, so even well-intentioned reuse of the Modeling Playground code or the curated `store_data.json` isn't legally clean. A license would let us — and the wider RiC community — build on your work with proper attribution, upstream contributions back to you, and no ambiguity. One commit on your side, a permanent contribution to the ecosystem.

In exchange, we'd be glad to:

- Credit you and DLIB prominently in UI, docs, and repo (`CREDITS.md`, commit messages, header comments on ported files).
- Cite Ionian University and the Laboratory of Digital Libraries in any academic write-up of OpenRiC.
- Contribute improvements back upstream — bug fixes, a11y, i18n — if useful to you.
- Collaborate more broadly if there's interest; we're planning Phase 5 around graph visualisation of real archival holdings and your Playground work is directly relevant.

One concrete contribution we'd like to propose, if you're open to PRs: a clearer separation between **declared** and **inherited** attributes and relations on entity and relation pages, in the style of the CIDOC-CRM reference page (`cidoc-crm.org/html/cidoc_crm_v7.1.3.html`, which uses a "Show all properties" toggle and tags each inherited row with the ancestor it comes from). We've noticed that the current flattening — where, for example, a relation with Domain *Agent* is rendered as Domain *Agent + Person + Group + Mechanism + Family* — can read as a semantic claim about the relation rather than a browsing aid, and the same applies when an entity's inherited relations are shown alongside its declared ones without provenance. Happy to scope this as a proposal first and a PR only if it aligns with your direction for the tool.

Happy to answer any questions about OpenRiC, scope, licensing intent, or how your work would be presented. Repo: [OPENRIC_REPO_URL]. Project lead contact: johan@theahg.co.za.

Thank you for considering — and regardless of the answer, thank you for the NavTool.

Kind regards,
[YOUR NAME]
[YOUR TITLE / AFFILIATION]
OpenRiC — [OPENRIC_REPO_URL]

---

## Before sending — checklist

- [ ] Replace `[OPENRIC_REPO_URL]` with the public OpenRiC GitHub URL (2 occurrences).
- [ ] Replace `[YOUR NAME]` and `[YOUR TITLE / AFFILIATION]` in the signature.
- [ ] Confirm Damigos's current email from the Ionian University staff page.
- [ ] Decide on the reply archive: save any reply (and granted permission) to `docs/outreach/damigos-reply.md` or `docs/PERMISSIONS.md`.
- [ ] If he grants via email rather than adding a LICENSE, ensure the email explicitly covers: reuse, modification, redistribution under OpenRiC's license, and attribution terms — then archive it in the repo.

## Notes on framing

- Ask #2 (the LICENSE) is pitched as an ecosystem benefit, not a personal favour. Don't soften that — it's the right angle for an academic audience.
- Tone is warm/formal (academic register). Loosen if you know him informally.
- We are NOT asking for rights to the RiC-CM 1.0 text content itself — that traces to ICA/EGAD and needs separate acknowledgement in OpenRiC, not permission from Damigos.
