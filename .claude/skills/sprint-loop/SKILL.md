---
name: sprint-loop
description: Drive one Trello card (UMR-0xx) to "acceptance-ready" by looping plan → implement → verify → review → sync, task by task, stopping only for PO questions, gates, or repeated failures. Use when the user says "work on UMR-004", "continue the sprint", "next card", or runs /loop /sprint-loop. Without an argument it takes the card of the current NOW.md step.
---

# Sprint loop (one card, many tasks)

Plan: `/Users/ramadan/Umrany/implementation-plan/` · card tool `.tools/umr` · order: `ladder.md` → live position `NOW.md` (`.tools/where`).

## 0. Where are we? (always first)
Run `/Users/ramadan/Umrany/implementation-plan/.tools/where` (= NOW.md). If it lists **missed / out-of-order steps**, tell
the user and fix those first (or get an explicit "defer"). The step's **"Ask the PO first"** list = open decisions for this
step: ask them now with AskUserQuestion (≤4 per call), never apply a proposed default silently; record answers via
plan-sync (`**Answered YYYY-MM-DD:**` in open-decisions.md). Then, at every step, review for anything missing and add it
(`GAP:` / new `PLAN-Q`) — open items are resolved at implementation time, not left for later.

## 0b. Pick the card
Argument `UMR-0xx`, else the card of the current NOW.md step. `.tools/umr UMR-0xx` → read ACs, Back checklist, gate.
Gate is an open decision (e.g. CR-DEC-07) → build only preparatory parts; never fake the gated behavior.

## 1. Plan the card (once per card)
If the card's ladder row has an empty **Tasks** column or the feature file has no "Card UMR-0xx coverage" block → run the **card-planner** agent.
It returns PO questions → **ask them now with AskUserQuestion (≤4 per call)**, record answers via plan-sync, then continue.
Never apply a "proposed" answer that the user hasn't confirmed.

## 2. Task loop — repeat until every AC in the coverage block is covered by ticked tasks
For the next unticked task id of this card:
1. Run the **next-task** skill on that id (narrow context → design → implement → verify in Docker).
2. Verification loop: lint + analyse + `migrate:fresh --seed` + curl checks (+ `core:lookups:verify` for LKP work).
   Fix and retry; **3 failed rounds on the same error → stop the loop and report** (don't thrash).
3. Reviews in parallel: **gap-reviewer** (story + card ACs), **perf-reviewer** (if queries/cache), **workbook-auditor** (if `lkp_*`/seed data touched). Fix blocking items, re-verify.
4. **plan-sync** agent: tick task, GAPs, pointer. (The Stop hook enforces this.)
5. Stop conditions (end the loop, tell the user why): a new business question · a decision conflict (D-LKP-*/PLAN-D-*) · a gate blocks the next task · 3 failed verify rounds · context getting long (suggest `/compact` then `/sprint-loop` again — the pointer makes it resumable).

## 3. Card acceptance check
When all tasks are ticked: run **gap-reviewer** with "card UMR-0xx" (it checks every card AC end-to-end, not one story),
then produce the **acceptance note** for the PO: each AC → how it's satisfied (endpoint/test/command), deferred items with
gate ids, Trello checklist items that can be ticked. Update `reference/trello-sprint-map.md` status; `.tools/where --write` moves NOW.md to the next step (also refresh the CURRENT POINTER summary in `00-master-plan.md`).
Do not commit or touch Trello unless the user asks.

## Running unattended
`/loop /sprint-loop UMR-002` (self-paced). Each iteration resumes from the plan pointer; the loop must call
ScheduleWakeup with `stop: true` on any stop condition above, so it never guesses past a question.
