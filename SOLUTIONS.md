# Solutions

Spoilers. Every number here was produced by running the lab on this repository (TYPO3 12.4.45 → 13.4.34, PHP 8.2).

---

## Lab 00

Unit: `OK (5 tests, 22 assertions)`.
Functional: `OK, but there were issues!` — `Tests: 8, Assertions: 11, Deprecations: 4`, exit code **1**.

1. `Build/phpunit/FunctionalTests.xml` sets `failOnDeprecation="true"` — the same
   setting the TYPO3 Core uses for its own suites. Any `E_USER_DEPRECATED` raised
   while the test runs fails the build, regardless of assertions.
2. Turning it off makes the build green and blinds you to the very thing you are
   preparing for. Keep it on. If the noise is unbearable *today*, cap it per
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
   found only by the scanner **10**, only by Rector **2**, only by Fractor **1**,
   only by the TCA check **9**. Just **3** of the scanner's 13 findings are
   auto-fixed by Rector.
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

## Lab 07

Rector changes **6 files**. The interesting one is `ext_localconf.php`:

```diff
     [\Webvision\FlightOps\Controller\FlightController::class => 'list'],
-    []
+    [],
+    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
```

3. The plugin moves from a `list_type` subtype to a real `CType`. Every existing
   `tt_content` row still says `CType=list, list_type=flightops_board` — so after
   this change the plugin matches nothing and renders nothing. The code is
   correct and the site is broken.
4. Rector generates `Classes/Updates/WebvisionFlightOpsCTypeMigration.php`, an
   upgrade wizard extending `AbstractListTypeToCTypeUpdate`, containing:

   ```php
   return [
       // TODO: Add this mapping yourself!
   ];
   ```

   Ship it unfinished and `typo3 upgrade:list` throws:
   *"does not provide a 'list_type' to 'CType' migration mapping"*. Fill it in:

   ```php
   return ['flightops_board' => 'flightops_board'];
   ```
5. `FlightBoardTest` — with
   `TypoScript object path "tt_content.list.20.flightops_board" does not exist`.
   It is the only test that renders the plugin, so it is the only one that can
   see that the plugin is no longer wired to anything. Rector also fixes the TCA
   here: 9 of the 10 TCA migrations have rector rules, leaving only
   `t3editor` → `codeEditor`.

## Lab 08

The full sequence, measured on this project — the same eight tests throughout:

| Stage                            | Functional suite                                | Exit |
|----------------------------------|-------------------------------------------------|------|
| 12.4, before departure           | 8 tests · 11 assertions · **4 deprecations**    | 1    |
| 13.4, straight after the bump    | 8 tests · 2 assertions · **6 errors** · 4 depr. | 2    |
| 13.4, after Rector and Fractor   | 8 tests · 6 assertions · **3 errors** · 1 depr. | 2    |
| 13.4, after the hand work        | **OK — 8 tests · 11 assertions**                | 0    |

The six errors after the bump: three × `Call to undefined method
QueryBuilder::execute()` (BoardingServiceTest) and three × `Undefined constant
FlashMessage::WARNING` (FlightBoardTest).

1. **Errors** cannot run at all — fix now. **Deprecations** still work — schedule
   them, but before the next major, not "sometime".
2. Rector fixed `QueryBuilder::execute()`, `FlashMessage::WARNING` and
   `lastTypoLinkUrl`. It could not fix:
   - **`TYPO3_mainDir`** — a removed global constant. There is no mechanical
     replacement; you have to decide what the code actually wanted.
   - **`t3editor` → `codeEditor`** — the one TCA migration with no rector rule.
   - **the orphaned content records** — a data problem, not a code problem.
3. The third one. See Lab 07.
4. `CType=list, list_type=flightops_board` becomes `CType=flightops_board`. The
   production equivalent is the upgrade wizard you completed in Lab 07 — run
   against real data in Lab 09. Fixtures are content records; they need the same
   migration your editors' content needs.
5. **10 findings (5 strong, 5 weak)** on 13.4, against 13 on 12.4. And the v13
   scanner reports `TypoScriptFrontendController` (`Deprecation-105230`), which
   the v12 scanner could not have known about.
6. Four, in this project: `QueryBuilder::execute()` (no scanner rule), the TCA
   migration (backend-only, no CLI), the `list_type` registration deprecation
   (only visible when the plugin is actually rendered), and the orphaned content
   records (invisible to every static tool that exists).

## Lab 09

1. It is not a core command, on no version. `database:updateschema` comes from
   TYPO3 Console (`helhum/typo3-console`): since Console 8.0 as
   `typo3 database:updateschema`, before that through its own binary as
   `typo3cms database:updateschema`. The core's schema step on the command line
   is `extension:setup`, the same command as in Lab 00, on 12.4 and on 13.4. The
   13.4.x changelog entry `Important-110454` still names the Console command.
   So a deployment script calls what is installed, not what a changelog, a blog
   post or a colleague names. Check it against `typo3 list` of the target
   version, as part of the upgrade.
2. `extension:setup` only executes the safe statements: tables and fields are
   created and altered, nothing is dropped or renamed. Removals happen in
   *Admin Tools › Maintenance › Analyze Database Structure* (or with TYPO3
   Console's destructive update types). Do them later, once the new version runs
   in production and a rollback to the old code is off the table. The old code
   may still need those fields.
3. `webvisionFlightOpsCTypeMigration`, your own wizard from Lab 07. It rewrites
   `tt_content` rows from `list_type` to the new `CType` and fixes backend user
   permissions. That is the production counterpart of the fixture edit in Lab 08.
   If it is missing from the list, flush the caches: the service container was
   built before the class existed.
4. The rest only appear when they have data to migrate. This instance is nearly
   empty, so most report nothing. On a copy of production data the list is
   longer, which is why you rehearse there.
5. The schema update together with the wizards. `extension:setup` only adds and
   alters, so the old code often still runs against the new tables, but an
   altered column and a wizard that rewrote records cannot be undone. Before
   them: a branch and a lock file. After them: a restore.

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
