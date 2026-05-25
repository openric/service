Problem

Reference browser UI (3D graph) is not wired or built. Non-technical users cannot browse records via the intended visualization.

Tasks

- Create a new route and controller action to serve the reference browser view.
- Add a Blade view and Vite entry for the 3D graph using three + 3d-force-graph.
- Wire API token/auth if needed for client-side SPARQL calls or provide server-side proxy endpoints.
- Add basic styles and instructions in docs.

Acceptance criteria

- /reference/browser loads the 3D graph and can render at least one record node.
- Build pipeline (npm run build) includes the new entry.

Estimated time: 4–8 hours.