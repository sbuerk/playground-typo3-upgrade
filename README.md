# Fly the Upgrade — TYPO3 Upgrade Workshop

A deliberately outdated TYPO3 **12.4 LTS** project with a deliberately outdated
extension, plus a test suite that already tells you what 13.4 is going to take
away.

Your job: get it to **13.4 LTS** with the pipeline green again — using the same
three phases as the talk.

> **Pre-Flight → Flight → Post-Flight.**
> The tests are not homework. They are the instrument panel.

---

## What is in the box

| Path                              | What                                                            |
|-----------------------------------|-----------------------------------------------------------------|
| `packages/flight_ops/`            | The extension you will be upgrading                             |
| `packages/flight_ops/Tests/`      | Unit, functional and frontend tests — your instrument panel     |
| `Build/Scripts/runTests.sh`       | One entry point for tests, scanner, rector, fractor             |
| `Build/phpunit/`                  | PHPUnit configs (`failOnDeprecation="true"`, like the Core)     |
| `rector.php` / `fractor.php`      | You create these in Lab 04 — deliberately not shipped           |
| `LABS.md`                         | The exercises                                                   |
| `SOLUTIONS.md`                    | Spoilers. Read after trying.                                    |
| `docs/`                           | The talk slides and the upgrade checklist                       |

`flight_ops` is small on purpose, but it is broken in **five different ways**,
and each one is found by a different tool:

| Broken thing                                   | Found by                          |
|------------------------------------------------|-----------------------------------|
| Deprecated / removed PHP API                    | Extension Scanner                 |
| `QueryBuilder->execute()`, `clearCacheOnLoad`   | Rector *only* — no scanner rule   |
| Legacy TypoScript (`INCLUDE_TYPOSCRIPT`, conditions) | Fractor *only*              |
| Legacy TCA (auto-migrated, silently)            | TCA migration check + **the tests** |
| A `list_type` plugin registration               | **Only** a test that renders the plugin |

---

## The talk and the checklist

The slides and the checklist that go with this workshop are in `docs/`.

| File                                | What it is                                                    |
|-------------------------------------|---------------------------------------------------------------|
| `fly-the-upgrade-slides.pdf`        | The full talk, 88 slides — the handout version                 |
| `fly-the-upgrade-slides.html`       | The same talk, self-contained and presentable in a browser     |
| `fly-the-upgrade-slides.pptx`       | The editable original                                          |
| `upgrade-flight-checklist.pdf`      | The three-phase checklist, A4, print it and tick it            |
| `upgrade-flight-checklist.md`       | The same checklist with `- [ ]` boxes, for repos and issues    |
| `upgrade-flight-checklist.html`     | The same checklist as a page, with the boxes clickable         |

Open the HTML deck straight from disk — no server needed. Presenter keys:
`→ ␣ N` next · `← P` previous · `Home`/`End` · `O` slide overview · `F` fullscreen
· `?` help. Deep-link a slide with `#42`.

> **If the PPTX looks wrong, it is the fonts.** It names Barlow, Barlow Condensed
> and IBM Plex Mono. Without them installed, PowerPoint and LibreOffice substitute
> a proportional face and every terminal block in the deck loses its alignment.
> The PDF and the HTML are unaffected — use those if you would rather not install
> anything.

The checklist is the same one used in the talk, consolidated into one document:
what to answer before you leave, the order to work in during the upgrade, and what
to do after landing so the next upgrade starts from a better place.

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
Tests: 8, Assertions: 11, Deprecations: 4.
```

Every assertion passes. The suite still exits **1**.

Because `Build/phpunit/FunctionalTests.xml` sets `failOnDeprecation="true"` — the
same setting the TYPO3 Core uses — every deprecation TYPO3 raises at runtime
becomes a build failure. And those four deprecations are, precisely:

1. the automatic TCA migration TYPO3 performs on every request,
2. `QueryBuilder::execute() will be removed in TYPO3 v13.0`,
3. the fourth argument of `GeneralUtility::intExplode()`, and
4. the `FlashMessage::WARNING` severity constant.

Three of the four only show up because one test renders the plugin through a real
frontend request. Coverage is not an abstraction here — it is the difference
between two findings and four.

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

The whole workshop in one table. Same eight tests, four moments — every number
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
- The **last red** is hand work: a removed constant, one TCA migration Rector has
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
| 00  | —           | Get airborne, and read what the suite already knows   |
| 01  | Pre-Flight  | Read the NOTAMs — changelogs, and why composer says no |
| 02  | Pre-Flight  | Extension Scanner: backend, CLI, strong vs weak        |
| 03  | Pre-Flight  | The silent one: TCA auto-migrations                    |
| 04  | Pre-Flight  | Reconnaissance: Rector and Fractor dry runs            |
| 05  | Pre-Flight  | Write the QRH, then Go / No-Go                         |
| 06  | Flight      | The bump — and the conflict you will hit               |
| 07  | Flight      | Rector and Fractor for real, *after* the bump          |
| 08  | Flight      | Get the pipeline green again                           |
| 09  | Flight      | Wizards, schema, caches                                |
| 10  | Post-Flight | Scan again, deprecation log, debrief                   |

Timing: labs 00–05 take about 2¼ hours, labs 06–09 about 2 hours, and lab 10
about half an hour. Comfortably a full day with breaks, or two half-days split
after lab 05 — which is also the natural break, because that is where the
preparation ends and the upgrade begins.

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
