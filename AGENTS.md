# AGENTS.md

## Project
Laravel roleplay chat app using Blade, Tailwind, Filament, SQLite in dev, MySQL in production, deployed through Forge.

## Working rules
- Prefer small, focused changes.
- Do not rewrite architecture unless explicitly asked.
- Do not remove existing behavior to simplify code.
- Preserve moderation/report/ban functionality.
- Preserve soft-delete/audit behavior.
- Do not change deployment configuration unless explicitly asked.
- Do not introduce new dependencies without asking.

## Security rules
- Never trust client-submitted user_id or character_id.
- Characters belong to users. Always enforce ownership server-side.
- DMs are conversation/room-like structures, not a totally separate special case.
- Admin routes must stay protected.
- Filament access must remain admin-gated.

## Before proposing a PR
- Explain what changed.
- Explain risk areas.
- Run relevant tests if available.
- For Laravel changes, prefer `php artisan test`.
- For migrations, explain rollback impact.