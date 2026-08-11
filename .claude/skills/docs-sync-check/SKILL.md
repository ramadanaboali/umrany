---
name: docs-sync-check
description: Verify that docs/ and CLAUDE.md files were actually updated to match a code change (root CLAUDE.md Rule 5). Run before considering any non-trivial change, endpoint, migration, or architectural decision "done."
---

# Check that docs actually stayed in sync

Root `CLAUDE.md` Rule 5 says documentation updates happen in the same change as the code, not as a follow-up. This skill is the check that catches when that didn't happen.

## 1. Work out what changed

```bash
git status --short
git diff --stat
```

Group the changed files by what they represent: a new/changed entity or migration, a new/changed route, a new package or architectural decision, a new cross-module contract/event, a new skill-worthy workflow, a business-facing change.

## 2. For each category of change, confirm the matching doc moved too

| You changed... | ...this doc must reflect it |
|---|---|
| A migration/model/entity in `Modules/<X>` | `Modules/<X>/CLAUDE.md` entity list **and** `docs/modules/<x>.md` |
| A route/controller | Scribe doc-blocks (`@group`/`@bodyParam`/`@response`) on the controller method, and `docs/api/conventions.md` if it introduces a new pattern (not just a new endpoint using existing patterns) |
| A new package, service, or infra choice | A new `docs/decisions/NNNN-*.md` ADR, plus the relevant `docs/architecture/*.md` table (tech-stack.md, infrastructure.md, or system-architecture.md) |
| A new `Contracts\...` interface or `Events\...` class | `docs/architecture/module-boundaries.md` (does the existing "bad vs. good" narrative still match reality?) |
| A new repeatable workflow you'd want Claude to follow again | A new or updated `.claude/skills/*/SKILL.md`, and the **Skills** list in root `CLAUDE.md` |
| Business scope, revenue model, or persona | `docs/business/*.md`, and `docs/business/roadmap.md` if it changes phase scope/sequencing |

Use a targeted grep to find what currently documents the area you touched, rather than assuming:

```bash
grep -rl "<keyword>" docs/ Modules/*/CLAUDE.md CLAUDE.md
```

## 3. Read the matched docs, not just their existence

A file existing under the right name isn't enough — open it and confirm the specific fact you changed (a field name, a status enum, a dependency direction) is actually reflected, not just that the file wasn't deleted.

## 4. Report

For each category from step 1 that has no matching doc update, say so explicitly and either fix it now or tell the user it's missing — don't silently let a change ship undocumented. For everything that *does* have a matching update, no need to narrate it — the absence of a complaint is the pass.
