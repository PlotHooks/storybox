# Security Invariants

These rules are intentionally small and operational. They describe the boundaries that must hold across public rooms, DMs, realtime subscriptions, unread state, and moderation.

- Never trust client-submitted `character_id` without server-side ownership validation.
- All message and conversation mutations require an authenticated, authorized user.
- Character actions require character ownership.
- Conversation mutations require current conversation access or participant membership.
- Public room read state must not be mutated from `session('active_character_id')`.
- Read/unread mutation must happen only through explicit request paths that include a validated character identity.
- DM sends must derive sender identity from `dm_participants` where possible.
- Admin overrides must be gated server-side.
- UI restrictions are never sufficient authorization.
- WebSocket subscriptions are authorized at subscription time. Stale subscription behavior after reconnect, tab switching, or membership changes is a known accepted residual risk unless later mitigated.
