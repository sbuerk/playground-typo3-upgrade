# Solutions

Spoilers. Every number here was produced by actually running the lab on this
repository (TYPO3 12.4.45 → 13.4.34, PHP 8.2).

---

## Lab 00

Unit: `OK (5 tests, 22 assertions)`.
Functional: `OK, but there were issues!` — `Tests: 5, Assertions: 6, Deprecations: 2`, exit code **1**.

1. `Build/phpunit/FunctionalTests.xml` sets `failOnDeprecation="true"` — the same
   setting the TYPO3 Core uses for its own suites. Any `E_USER_DEPRECATED` raised
   while the test runs fails the build, regardless of assertions.
2. Turning it off makes the build green and blinds you to exactly the thing you
   are preparing for. Keep it on. If the noise is unbearable *today*, cap it per
   test with a baseline — do not switch it off globally.
3. The two:
   - `TcaFactory.php:176` — "Automatic TCA migration done during bootstrap …
     these migrations will be removed."
   - `QueryBuilder.php:254` — "QueryBuilder::execute() will be removed in TYPO3 v13.0."

---

## Lab 01

1. Free support for 12.4 ended **30 April 2026**, so the last public release is
   frozen while advisories keep being published. Options: buy **ELTS**, **upgrade**,
   or knowingly run unpatched. `--no-security-blocking` silences the message, not
   the vulnerability.
2. / 3. `Deprecation-96972` (QueryBuilder::execute) and the TCA migration
   messages, each with a Migration section.

---

## Lab 02

```
Total issues: 13 (6 strong, 7 weak)
```

1. **strong** = the scanner resolved the type and is certain (e.g. a static call
   `BackendUtility::getUpdateSignalCode()`). **weak** = the name matched but the
   type could not be resolved (`$obj->getTreeList()` might be TYPO3's, or yours).
3. There is **no scanner rule** for `QueryBuilder->execute()`. The scanner is a
   fixed list of matchers, not a semantic analysis of the core.
4. `--fail-on-weak` would fail the build on findings that are often false
   positives. Fail on strong, report weak, and triage weak by hand.

---

## Lab 03

Nine messages on 12.4: `flight_number` (required-in-eval), `crew_mail` (email),
`seats` (eval=int), `scheduled` (inputDateTime), `booking_link` (inputLink),
`livery_color` (colorpicker), `status` (legacy items array), `spacer` (cols→size),
`ctrl.cruser_id`.

1. Nothing is broken *today*. TYPO3 rewrites the TCA in memory on every single
   request, and that rewrite is scheduled for removal. The day it goes, your
   configuration is simply ignored — silently.
2. "Automatic TCA migration done during bootstrap. Please adapt TCA accordingly,
   **these migrations will be removed**. The backend module 'Configuration -> TCA'
   shows the modified values."
3. There is none. Verified with `typo3 list` — no `tca:*` command exists. The
   check is backend-only, which is exactly why it gets skipped, and exactly why
   the functional test matters.
4. `'eval' => 'trim,required'` → `'eval' => 'trim', 'required' => true`.

---

## Lab 04

`rector.php`:

```php
<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/packages/flight_ops'])
    ->withSets([Typo3LevelSetList::UP_TO_TYPO3_13]);
```

`fractor.php`:

```php
<?php
declare(strict_types=1);

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;

return FractorConfiguration::configure()
    ->withPaths([__DIR__ . '/packages/flight_ops'])
    ->withSets([Typo3LevelSetList::UP_TO_TYPO3_13]);
```

Rector: **4 files**, rules `RenameClassConstFetchRector`,
`MigrateTypoScriptFrontendControllerTypeRector`, `MigrateQueryBuilderExecuteRector`,
`MigrateContentObjectRendererLastTypoLinkPropertiesRector`, `ExtEmConfRector`.

1. `QueryBuilder->execute()` and `clearCacheOnLoad` — no scanner rules exist for
   either.
2. `Configuration/TypoScript/setup.typoscript` — `INCLUDE_TYPOSCRIPT` → `@import`
   and the legacy conditions → Symfony expression syntax.
3. Coverage table (this is the slide "Four tools, four blind spots"):
   found only by the scanner **10**, only by rector **2**, only by fractor **1**,
   only by the TCA check **9**. Just **3** of the scanner's 13 findings are
   auto-fixed by rector.
4. The rule sets ship with the tool; the code they must match ships with the new
   core. Refactoring before the bump rewrites your code against the old world,
   and you will do it twice.

---

## Lab 06

The first failure:

```
Problem 1
  - Root composer.json requires webvision/flight-ops @dev
  - webvision/flight-ops require typo3/cms-core ^12.4 -> found
    typo3/cms-core[v12.4.0, ..., v12.4.45] but it conflicts with your root
    composer.json require (^13.4).
```

1. **Your own extension.** `packages/flight_ops/composer.json` still requires
   `typo3/cms-core: ^12.4`. Local path packages are part of the same dependency
   graph as everything else — raise it to `^13.4` there too.
2. Yes. With both constraints raised, `ddev composer update -W` resolves and
   installs **13.4.34**.
3. `typo3/testing-framework` **^9** for 13.4 (^8 is the 12.4 line).
4. `composer require typo3/cms-core:^13.4 -W` fails differently: it reports the
   remaining `typo3/cms-*` packages as *"locked to version v12.4.45 and an update
   of this package was not requested"*, and it also trips the advisory block.
   `require` changes one constraint and tries to keep the rest of the lock;
   a major core bump moves twenty-five packages at once. Edit the constraints,
   then `update -W`.
5. In this project roughly 60 packages move — including doctrine/dbal 3 → 4.
   That is the real blast radius of a "one line" version bump.

**The dev-tooling variant.** On a project where the refactoring tools are pinned
harder in the lock, the same bump fails on them instead — the sibling demo for
this talk hit:

```
- typo3/cms-install[v13.4.10, ...] require nikic/php-parser ^5.4.0
  -> found nikic/php-parser[v5.4.0, ...] but these were not loaded,
     likely because it conflicts with another require.
```

…because `ssch/typo3-rector` pinned `nikic/php-parser` in `require-dev`. Same
lesson, different package: move the tools in the same transaction, or install
them in their own `tools/` directory so they can never veto production
dependencies again.

## Lab 08

The full sequence, measured on this project — the same five tests throughout:

| Stage                                | Result                                          | Exit |
|--------------------------------------|-------------------------------------------------|------|
| 12.4, before departure               | 5 tests · 6 assertions · **2 deprecations**     | 1    |
| 13.4, straight after the bump        | 5 tests · 2 assertions · **3 errors** · 1 depr. | 2    |
| 13.4, after Rector + Fractor         | 5 tests · 6 assertions · **1 deprecation**      | 1    |
| 13.4, after fixing the TCA by hand   | **OK — 5 tests · 6 assertions**                 | 0    |

1. **Errors** (3): `Call to undefined method QueryBuilder::execute()` in
   `BoardingService::findDepartures()`, hit by three tests. Removed API — the code
   cannot run at all. **Deprecation** (1): the TCA auto-migration. Still works,
   scheduled for removal, and it is what keeps the build red after rector.
2. Rector fixed **all three errors** by itself (`MigrateQueryBuilderExecuteRector`,
   plus the FlashMessage and lastTypoLinkUrl migrations). It cannot fix the TCA —
   that is Lab 03's hand work, and it is the last thing standing between you and
   a green build.
3. The scanner reports **11** findings on 13.4 (6 strong, 5 weak) against 13 on
   12.4. Three were cleared by rector, and one new strong finding appeared:
   `TypoScriptFrontendController` (`Deprecation-105230`) — a v13 entry the v12
   scanner could not have known about.
4. Two problems were surfaced by the tests and by nothing else in the pre-flight
   phase: `QueryBuilder::execute()` (no scanner rule exists) and the TCA migration
   (backend-only, no CLI). Both were in the very first test run, in Lab 00.

## Lab 09

1. `database:updateschema` does not exist in 12.4 — `extension:setup` applies the
   schema there. Command surfaces change between majors, so an upgrade script
   written for the old version does not necessarily run on the new one. Check
   your deployment scripts as part of the upgrade, not after it.
2. Wizards only appear when they have data to migrate. This instance is nearly
   empty, so most report nothing. On a copy of production data the list is
   longer — which is why you rehearse there.
3. The schema change. Before it: a branch and a lock file. After it: a restore.

---

## Lab 10

1. The Extension Scanner ships **with the core**, so on 13.4 it carries 13.4's
   rule set. Same tool, same code, new truth.
1b. **Ten** TCA messages on 13.4 against nine on 12.4 — v13 adds
   `t3editor` → `codeEditor`. The list you cleared is not the list you get.
2. It is not today's problem — it is the first line of the *next* upgrade's plan.
   Put it in the backlog with the changelog link attached, while you still
   remember why.
4. Anything installed only to perform the upgrade: rector, fractor, the scanner
   CLI. Either remove them or move them into an isolated `tools/` install — see
   Lab 06.
