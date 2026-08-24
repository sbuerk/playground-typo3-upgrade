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

The failure:

```
Problem 1
  - Root composer.json requires typo3/cms-install ^13.4 …
  - typo3/cms-install[v13.4.10, …] require nikic/php-parser ^5.4.0
    -> found nikic/php-parser[v5.4.0, …] but these were not loaded,
       likely because it conflicts with another require.
```

1. `ssch/typo3-rector` pins `nikic/php-parser` in `require-dev`.
2. Because composer resolves one graph for the whole project — `require-dev` is
   part of it.
3. Quick: update the tools in the same transaction (`ddev composer update -W`
   with the tool constraints raised). Structural: move dev tooling into its own
   `tools/` install (e.g. `bamarni/composer-bin-plugin`) so it can never veto a
   production dependency again.
4. `typo3/testing-framework` **^9** for TYPO3 13.4 (^8 is the 12.4 line). The
   version matrix is in its README — this is a "read the extension's own upgrade
   instructions" moment.

---

## Lab 08

Failures fall into two groups:

**Fatal (removed API)** — the test errors, not fails:
- `QueryBuilder::execute()` gone → `Call to undefined method`
- `ContentObjectRenderer->lastTypoLinkUrl` gone
- `FlashMessage::WARNING` gone → `ContextualFeedbackSeverity::WARNING`

**Deprecation (still works, scheduled)** — the assertion passes, the suite fails:
- TCA auto-migration (if you did not finish Lab 03)
- `TypoScriptFrontendController` itself, new in 13 (`Deprecation-105230`)

3. The scanner reports **11** findings on 13.4 (6 strong, 5 weak) where it
   reported 13 on 12.4 — rector cleared three, and one *new* strong finding
   appeared: `TypoScriptFrontendController`, a v13 deprecation the v12 scanner
   could not have known about.
4. Two problems were found by the tests and by nothing else in the pre-flight
   phase: `QueryBuilder::execute()` (no scanner rule) and the TCA migration
   (no CLI, backend-only). Both were in the test output in **Lab 00**, before
   anyone opened a changelog.

---

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
