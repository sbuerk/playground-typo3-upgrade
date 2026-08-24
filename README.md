# Fly the Upgrade — TYPO3 Upgrade Workshop

A deliberately outdated TYPO3 **12.4 LTS** project with a deliberately outdated
extension, plus a test suite that is **green on 12.4**.

Your job: get it to **13.4 LTS** with the pipeline green again — using the same
three phases as the talk.

> **Pre-Flight → Flight → Post-Flight.**
> The tests are not homework. They are the instrument panel.

---

## What is in the box

| Path                              | What                                                            |
|-----------------------------------|-----------------------------------------------------------------|
| `packages/flight_ops/`            | The extension you will be upgrading                             |
| `packages/flight_ops/Tests/`      | Unit + functional tests — **green on 12.4**                     |
| `Build/Scripts/runTests.sh`       | One entry point for tests, scanner, rector, fractor             |
| `Build/phpunit/`                  | PHPUnit configs (`failOnDeprecation="true"`, like the Core)     |
| `rector.php` / `fractor.php`      | You create these in Lab 04 — deliberately not shipped           |
| `LABS.md`                         | The exercises                                                   |
| `SOLUTIONS.md`                    | Spoilers. Read after trying.                                    |

`flight_ops` is small on purpose, but it is broken in **four different ways**,
and each one is found by a different tool:

| Broken thing                                   | Found by                          |
|------------------------------------------------|-----------------------------------|
| Deprecated / removed PHP API                    | Extension Scanner                 |
| `QueryBuilder->execute()`, `clearCacheOnLoad`   | Rector *only* — no scanner rule   |
| Legacy TypoScript (`INCLUDE_TYPOSCRIPT`, conditions) | Fractor *only*              |
| Legacy TCA (auto-migrated, silently)            | TCA migration check + **the tests** |

---

## Requirements

- [ddev](https://ddev.readthedocs.io/) ≥ 1.24 and Docker
- git
- ~2 GB disk

No local PHP needed — everything runs inside ddev.

---

## Quick start

```bash
git clone <this-repo> typo3-upgrade-workshop
cd typo3-upgrade-workshop

ddev start
ddev composer install          # installs from the committed lock file
ddev composer workshop:setup   # installs TYPO3, creates the admin user
ddev exec ./vendor/bin/typo3 extension:setup   # also applies the database schema
```

Backend: <https://t3upgrade-workshop.ddev.site/typo3/>
User `admin`, password `Upgrade.Demo!2024`.

Now prove the aircraft is airworthy **before** you touch anything:

```bash
./Build/Scripts/runTests.sh -s tests
```

Read what comes back carefully — this is Lab 00 and it is not what you expect:

```
--- unit tests ---
OK (5 tests, 22 assertions)

--- functional tests ---
OK, but there were issues!
Tests: 5, Assertions: 6, Deprecations: 2.
```

Every assertion passes. The suite still exits **1**.

Because `Build/phpunit/FunctionalTests.xml` sets `failOnDeprecation="true"` — the
same setting the TYPO3 Core uses — the two deprecations TYPO3 raises at runtime
become build failures. And those two deprecations are, precisely:

1. the automatic TCA migration TYPO3 performs on every request, and
2. `QueryBuilder::execute() will be removed in TYPO3 v13.0`.

**Your test suite just did your pre-flight scan for you, while you are still on
12.4.** No scanner, no rector, no changelog reading — the tests executed the code
and the core told them what v13 will take away.

That is the whole argument for coverage in one command.

> **Why is `composer.lock` committed?**
> TYPO3 v12 left free support on 30 April 2026. A fresh dependency resolve is
> now refused by composer because the last public 12.4 release is affected by
> published advisories. `composer install` from the committed lock still works.
> That is not a workaround for the workshop — it is the actual situation every
> v12 project is in right now, and it is Lab 01's first observation.

---

## What the pipeline does for you

The whole workshop in one table. Same five tests, four moments — every number
here was measured on this project, not estimated:

| Stage                            | Functional suite                                | Exit |
|----------------------------------|-------------------------------------------------|------|
| 12.4, before departure           | 8 tests · 11 assertions · **4 deprecations**    | 1    |
| 13.4, straight after the bump    | 8 tests · 2 assertions · **6 errors** · 4 depr. | 2    |
| 13.4, after Rector + Fractor     | 8 tests · 6 assertions · **3 errors** · 1 depr. | 2    |
| 13.4, after the hand work        | **OK — 8 tests · 11 assertions**                | 0    |

Read it top to bottom and the argument makes itself:

- On **12.4** the suite already names four things v13 changes. That is
  preparation, for free, before anyone opens a changelog.
- On **13.4** those deprecations have become six hard errors, with file and line.
  Today's deprecation really is tomorrow's breaking change — and your tests are
  where you watch it happen.
- **Rector** clears the mechanical half and then does something more interesting:
  it migrates the plugin registration and **silently orphans every existing
  content record**, scaffolding an upgrade wizard with a `// TODO` in it. The
  only thing that notices is the test that renders the plugin.
- The **last red** is hand work: a removed constant, one TCA migration rector has
  no rule for, and a data migration. The suite refuses to go green until all
  three are done.

Four of these problems are reported by **no other tool** in the pre-flight phase:
`QueryBuilder::execute()` (no Extension Scanner rule), the TCA migration
(backend-only, no CLI), the `list_type` registration deprecation (only visible
when the plugin actually renders), and the orphaned content records (invisible to
every static tool that exists).

The single most useful experiment in this repo: comment out `FlightBoardTest`,
run Lab 00 again, and watch the number of reported problems halve.

---

## The labs

See **[LABS.md](LABS.md)**. Short version:

| Lab | Phase       | Theme                                                  |
|-----|-------------|--------------------------------------------------------|
| 00  | —           | Get airborne: install, and get a green baseline        |
| 01  | Pre-Flight  | Read the NOTAMs — changelogs, and why composer says no |
| 02  | Pre-Flight  | Extension Scanner: backend, CLI, strong vs weak        |
| 03  | Pre-Flight  | The silent one: TCA auto-migrations                    |
| 04  | Pre-Flight  | Reconnaissance: rector and fractor dry runs            |
| 05  | Pre-Flight  | Write the QRH, then Go / No-Go                         |
| 06  | Flight      | The bump — and the conflict you will hit               |
| 07  | Flight      | Rector and Fractor for real, *after* the bump          |
| 08  | Flight      | Get the pipeline green again                           |
| 09  | Flight      | Wizards, schema, caches                                |
| 10  | Post-Flight | Scan again, deprecation log, debrief                   |

Timing: labs 00–05 are about 90 minutes, 06–09 about 60, lab 10 about 30.

---

## Resetting

Everything is under git, so you can always go back:

```bash
git stash                      # park your work
git checkout .                 # throw it away
git clean -fd packages/        # remove new files
```

Full reset including the database:

```bash
ddev delete -O t3upgrade-workshop
ddev start && ddev composer install && ddev composer workshop:setup
```

---

## For trainers

- Everyone hits the composer conflict in Lab 06. That is intentional — it is the
  "your own tooling can ground you" moment. Do not spoil it.
- Lab 03 is the one people have never seen. Budget extra time.
- The interesting number at the end of Lab 08 is *how many failures the tests
  caught that nothing else did*. Collect it from the room.
- `SOLUTIONS.md` has every command and every expected output.
