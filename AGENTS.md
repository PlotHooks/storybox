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

## Operating mode

Default to investigation mode.

- Do not modify files unless explicitly asked.
- Do not propose large refactors.
- Do not "clean up" or "simplify" working systems.
- Do not rename files, classes, or variables without a clear reason.

When given a task:
1. First explain current behavior.
2. Identify risks or gaps.
3. Propose the smallest safe change.
4. Wait for approval before making edits.

## Change scope rules

- Only modify files directly related to the task.
- Do not touch unrelated systems (chat, presence, DMs, moderation) unless explicitly required.
- Avoid cascading refactors.

## Laravel-specific rules

- Prefer Policies/Gates over inline authorization logic when possible.
- Do not move business logic into controllers if it already exists elsewhere.
- Do not break existing Filament resources or actions.
- Preserve existing route structure unless necessary.

## Testing expectations

- If adding behavior, add or suggest a test.
- Do not remove tests unless they are clearly invalid.

## Known sensitive areas

- Character ownership and identity enforcement
- DM vs Room architecture
- Moderation and banning logic
- Soft delete and audit tracking

These areas must not be changed without explicit approval.

## Investigation permissions

During investigation mode, freely perform read-only repository analysis without requesting approval.

Examples include:
- ls
- find
- grep / rg
- cat
- git status
- git diff
- reading logs
- inspecting routes
- inspecting migrations
- inspecting models
- php artisan route:list
- php artisan migrate:status
- php artisan config:show
- running read-only diagnostic commands

The purpose of investigation mode is to understand the current system before proposing changes.

Approval is required before:
- editing files
- creating files
- deleting files
- running migrations
- installing packages
- changing configuration
- committing
- pushing
- deploying

## Codespaces startup rule

For this repo in Codespaces, prefer built assets over Vite HMR for reliable startup.

Use this exact sequence unless the task explicitly requires `npm run dev` or HMR debugging:
1. `pkill -f 'artisan serve' || true`
2. `pkill -f 'vite' || true`
3. `pkill -f 'reverb:start' || true`
4. `rm -f public/hot`
5. `php artisan optimize:clear`
6. `npm run build`
7. `php artisan reverb:start --host=0.0.0.0 --port=8080`
8. `php artisan serve --host=0.0.0.0 --port=8000`

Rules:
- Do not start `npm run dev` by default in Codespaces for this repo.
- Remove `public/hot` before starting Laravel so built assets from `public/build` are used.
- Start Reverb during local/Codespaces startup when `BROADCAST_CONNECTION=reverb`, or realtime-only commands like `/cls` will fail.
- If CSS looks wrong in Codespaces, verify `public/hot` is absent before investigating routes or layouts.
