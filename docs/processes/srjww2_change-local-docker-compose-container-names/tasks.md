# Tasks: change local docker compose container names

id: srjww2
status: implemented — TASK-1 and TASK-2 done; TASK-3 (runtime verification) could not be run in this sandbox (no Docker available), needs to be done on a machine with Docker
analyst: architect
date: 2026-08-08

<!-- Produced by @analyst from brief.md. -->

## Context / investigation notes

- The **local** dev compose file is `/workspace/compose.yaml` (project name `solicms`,
  set via top-level `name: solicms`). It defines six services — `app`, `queue`,
  `scheduler`, `vite`, `redis`, `mailpit` — and none of them set `container_name`.
  Docker Compose therefore falls back to its default naming scheme
  `<project>-<service>-<replica>`, which produces `solicms-app-1`,
  `solicms-queue-1`, etc. This matches the brief's complaint exactly.
- `app`, `queue`, and `scheduler` share config via a YAML anchor (`x-php: &php`,
  lines 4–19). `container_name` must NOT be added to that shared anchor block —
  each service using the anchor would then try to claim the same container name
  and Compose would fail to start more than one of them. It must be set
  individually under each service key instead (`app:`, `queue:`, `scheduler:`),
  the same way `deployment/docker/compose.yml` already does it.
- There is a second, separate compose file for **production** deployment:
  `/workspace/deployment/docker/compose.yml`. It already sets
  `container_name: solicms-app` / `solicms-queue` / `solicms-scheduler` — it is
  out of scope and does not need changes.
- `/workspace/deployment/Makefile` already has a `shell:` target
  (`docker exec -it solicms-app bash`) for the production container. The root
  `/workspace/Makefile` (used for local dev via `make start` / `make stop`) has
  no such target — this is the one the brief is asking for.
- No other files in the repo (outside the two process-doc copies of brief.md)
  reference `solicms-app-1` or any of the other default container names, so
  there is nothing else to update for this change.
- Root `/workspace/Makefile` currently:
  ```
  start:
      docker compose up -d

  stop:
      docker compose down

  deploy:
      cd deployment && ansible-playbook -i hosts.ini deploy.yml
  ```

## Open question (not blocking, flagging for awareness)

If a developer ever ran both the local `compose.yaml` stack and the production
`deployment/docker/compose.yml` stack on the same Docker host at the same time,
the `app`/`queue`/`scheduler` container names would collide (`solicms-app` etc.
used by both). Given the architecture doc describes local dev machines and the
single production server as separate hosts, this is very unlikely in practice —
flagging only so it isn't a silent surprise, not proposing a task for it.

## Task breakdown

TASK-1: Add an explicit `container_name` to each of the six services in
`/workspace/compose.yaml` (`app` → `solicms-app`, `queue` → `solicms-queue`,
`scheduler` → `solicms-scheduler`, `vite` → `solicms-vite`, `redis` → `solicms-redis`,
`mailpit` → `solicms-mailpit`), setting it per-service rather than on the shared
`x-php` anchor.
     files: /workspace/compose.yaml
     depends: none
     risk: low — pure config change with no other consumers in the repo; the only
     subtlety is that `container_name` must be added per-service (not to the
     shared `x-php` anchor) to avoid three services claiming the same name.

TASK-2: Add a `shell` target to the root `/workspace/Makefile` that runs
`docker exec -it solicms-app bash`, mirroring the existing target already
present in `/workspace/deployment/Makefile`.
     files: /workspace/Makefile
     depends: TASK-1 (target references the new fixed name `solicms-app`; it
     will still work today by coincidence since Compose currently names the
     container `solicms-app-1`... actually it will NOT match until TASK-1 lands,
     so TASK-1 must land first for the target to resolve to a running container)
     risk: low — one-line Makefile addition, no other logic involved.

TASK-3: Recreate the local containers and verify the outcome: run
`docker compose down` followed by `make start` (or `docker compose up -d`), then
confirm `docker ps` shows `solicms-app`, `solicms-queue`, `solicms-scheduler`,
`solicms-vite`, `solicms-redis`, `solicms-mailpit` with no `-1` suffix, and
confirm `make shell` opens an interactive bash session inside `solicms-app`.
     files: none (manual/CLI verification only)
     depends: TASK-1, TASK-2
     risk: low — verification step only; the one thing to note is that renaming
     requires containers to be recreated (`down` + `up`), a simple `restart`
     will not pick up the new `container_name`, and this will briefly interrupt
     the local dev environment.
