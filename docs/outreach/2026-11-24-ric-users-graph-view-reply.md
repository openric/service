# Draft: RiC users list, graph views and loading RiC-O

Status: draft for Dr Johan Pieterse to read, change and post himself. Not sent.

Where: the Records_in_Contexts_users list. The thread it answers, "Draw.io shape library for Records in Contexts" (https://groups.google.com/g/Records_in_Contexts_users/c/jvPTj01KmU0), dates from March 2024. In it Richard Williamson said he would like to try Cytoscape.js for RiC graphs and asked whether anyone would experiment with it, and Arian Rajh pointed to WebVOWL. Replying there two and a half years later may look odd; a new post that links the thread may read better. Your call.

Before posting, note: the daily plan described the thread as Richard asking for a product-independent graph-view standard and Arian asking about RDF upload. The part of the thread that could be read does not show either. Richard asked for a Cytoscape.js experiment, and Arian suggested WebVOWL. The draft answers what the thread shows. Its later messages could not be read, so check them before you post.

## Suggested subject (new post)

Cytoscape.js graphs of RiC data, and loading RiC-O files: two open implementations

## Draft

Dear all,

In March 2024 Richard asked, in the thread on the draw.io shape library, whether anyone would try Cytoscape.js for RiC graphs, since the off-the-shelf visualisers did not handle individuals. We have, and it may be useful to others.

OpenRiC Workspace, an open-source archival description application built on RiC-O 1.1 (AGPL, https://workspace.openric.org), draws its graphs with Cytoscape.js. There are two views. One is the graph of a record and what it is linked to. The other is a name-first explorer for a record set: choose a person, place or event and see the records that carry it, double-click to drill down, and keep a trail back. The reference service for the OpenRiC specification (https://ric.theahg.co.za) uses Cytoscape.js too. Both show instances, which was Richard's point, not only the ontology's classes.

Behind the views is a small contract, not a product: a graph endpoint that returns nodes and edges, each node carrying the IRI the API gives that entity and typed with a RiC-O 1.1 class. It is written up as the Graph Traversal profile of the OpenRiC specification (https://openric.org), with SHACL shapes and a conformance check anyone can run against their own server. Any viewer, Cytoscape.js or another, can draw from it.

On loading data the other way: the Workspace imports RiC-O as JSON-LD, Turtle or RDF/XML. Every import starts as a dry run, which reports what each entry would become and what it could not take, before anything is written.

A worked example from a real collection, a 1950 newspaper scrapbook held by Smuts House Museum in South Africa, is at https://openric.org/learn/examples/, with links to the live records and their graphs.

OpenRiC is an independent project, not issued or endorsed by the ICA. Where it and RiC disagree, RiC is right, and we would be glad to hear where it falls short.

Kind regards
Dr Johan Pieterse
