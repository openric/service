# CLAUDE.md - OpenRiC

> **Workbench global rules apply.** This project follows the 14-step change workflow, testing autonomy, git read/write split, commit-message sanitiser, and file-lock handshake from `/var/lib/workbench/global_rules.md`. The chat injects them automatically. The rules below extend, not override.


## Project Overview

Standalone Laravel implementation of the OpenRiC reference API (RiC-O / SPARQL / SHACL endpoints). Decoupled from Heratio per the separation plan — graph + SPARQL + SHACL engine lives here; `ric_*` tables stay in Heratio.

Public reference site: ric.theahg.co.za.

## Rules

- NEVER commit or push directly. Stage with `git add`, then supply commands to the user.
- AGPL header on new files (Johan Pieterse / Plain Sailing iSystems).
- Each MCP server lives in its own folder under `.claude/mcp-servers/<name>/`.

## MCP Servers

- `heratio-km` — query the Heratio Knowledge Base at km.theahg.co.za (includes the RiC corpus). Use `source="ric"` for RiC-specific lookups.

## Knowledge Base

For OpenRiC spec / RiC-O modelling questions ask the KM:
`mcp__heratio-km__km_ask` with `source="ric"`.
