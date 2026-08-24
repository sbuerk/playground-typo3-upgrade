# Labs

Work through these in order. Each lab says what to do, what you should see, and
what it is teaching. Commands assume you are in the project root.

`SOLUTIONS.md` has the answers — use it after you have tried, or when a lab
says so explicitly.

---

## Lab 00 · Get airborne

**Phase: —  ·  ~15 min**

```bash
ddev start
ddev composer install
ddev composer workshop:setup
ddev exec ./vendor/bin/typo3 extension:setup
./Build/Scripts/runTests.sh -s tests
```

**Expected:** unit green, functional "OK, but there were issues!", exit code 1,
**four** deprecations.

### Questions

1. Every assertion passed. Why did the suite still fail?
2. Look up `failOnDeprecation` in `Build/phpunit/FunctionalTests.xml`. Would you
   turn it off? Argue both sides, then decide.
3. All four name something that changes in v13. Write them down — this is the
   first page of your upgrade plan, and you produced it in one command without
   reading a single changelog.
4. Three of the four are only reported because `FlightBoardTest` renders the
   plugin through a real frontend request. Comment that test class out and run
   again: you are down to two. **That difference is what coverage buys you.**

> **The point:** a test suite is not there to prove your code is correct. During
> an upgrade it is there to *execute* your code so the core can tell you what is
> about to be taken away. Static analysis cannot do that.

---

## Lab 01 · Read the NOTAMs

**Phase: Pre-Flight  ·  ~20 min**

```bash
# 1. What version are we on, and how long is it supported?
ddev exec ./vendor/bin/typo3 --version

# 2. Try to move a single core package forward
ddev composer update typo3/cms-core --dry-run
```

**Expected:** composer refuses, citing security advisories.

### Questions

1. Why is the last public 12.4 release blocked? What are your three options?
   (Hint: one of them costs money, one of them is this workshop.)
2. Open the changelog viewer in the backend:
   *Admin Tools › Upgrade › View Upgrade Documentation*. Filter to 13.0.
3. Find the entries behind the two deprecations from Lab 00. Each one has a
   **Migration** section — that is your instruction, already written for you.

---

## Lab 02 · Pre-flight scan

**Phase: Pre-Flight  ·  ~25 min**

Backend first: *Admin Tools › Upgrade › Scan Extension Files*, scan `flight_ops`.

Then the same thing on the command line:

```bash
./Build/Scripts/runTests.sh -s scan
```

**Expected:** 13 findings — 6 strong, 7 weak — and a non-zero exit code.

### Questions

1. Pick one **strong** and one **weak** match. Why is one certain and the other not?
2. Open a finding and follow it to its changelog entry. Do it in the backend —
   the scanner links them for you.
3. `QueryBuilder->execute()` is deprecated since 12.0. Find it in the scanner
   output. (You will not — there is no scanner rule for it. Your tests found it
   in Lab 00. Keep that in mind for Lab 04.)
4. Add the scan to CI. `.gitlab-ci.yml` / `.github/workflows/ci.yml` are already
   in the repo — read what they do and why `--fail-on-weak` is *not* set.

---

## Lab 03 · The silent one

**Phase: Pre-Flight  ·  ~25 min**

Open *Admin Tools › Upgrade › **Check TCA Migrations***.

**Expected:** nine messages about `flight_ops`.

### Questions

1. Nothing is broken. The backend works, the frontend works. So what exactly is
   the problem?
2. Read the deprecation text TYPO3 raises (you saw it in Lab 00). It contains a
   promise. Quote it.
3. Try to find a CLI command for this check. How long did you look?
4. Now fix **one** field — `flight_number` — in
   `packages/flight_ops/Configuration/TCA/tx_flightops_domain_model_departure.php`,
   then re-run:
   ```bash
   ./Build/Scripts/runTests.sh -s functional -e "--filter DepartureTca"
   ```
   The deprecation message should get one line shorter.
5. Fix the rest. When the TCA is clean, that whole deprecation disappears.
6. Note the count: **nine** on 12.4. Come back to this number in Lab 10 — the
   list is not the same length after the upgrade.

> **The point:** TCA auto-migration is the autopilot quietly trimming out an
> imbalance. Everything flies straight — until the day the autopilot is removed.
> Two things notice it: a backend module nobody opens, and your test suite.

---

## Lab 04 · Reconnaissance

**Phase: Pre-Flight  ·  ~25 min**

Neither `rector.php` nor `fractor.php` exists yet. Create them — target **v13**:

```bash
ddev exec ./vendor/bin/typo3-init          # writes rector.php
ddev exec ./vendor/bin/typo3-fractor-init  # writes fractor.php
```

Point both at `packages/flight_ops` and use the `UP_TO_TYPO3_13` level set,
then look — do not write yet:

```bash
./Build/Scripts/runTests.sh -s rector
./Build/Scripts/runTests.sh -s fractor
```

### Questions

1. Rector reports something the Extension Scanner never mentioned. What, and why?
2. Fractor reports a file neither of the other two ever opened. Which one?
3. Build the coverage table for yourself:

   | Finding | Scanner | Rector | Fractor | TCA check | Tests |
   |---------|---------|--------|---------|-----------|-------|

   Fill one row per problem in `flight_ops`. How many are found by **exactly
   one** tool?
4. Why must rector and fractor run *after* the package upgrade, not now?

---

## Lab 05 · QRH and Go / No-Go

**Phase: Pre-Flight  ·  ~20 min**

Write two documents. Actually write them — this is the lab.

1. **`QRH.md`** — for each problem you found, one row: what will break, what you
   will do, and the changelog reference.
2. **Go / No-Go** — your criteria for starting the upgrade. Be specific enough
   that someone else could apply them without you in the room.

Then decide, out loud, as a group: **Go or No-Go?**

---

## Lab 06 · The bump

**Phase: Flight  ·  ~30 min**

Freeze first — in real life this is the content and deploy freeze. Here:

```bash
git checkout -b upgrade/v13
git add -A && git commit -m "Pre-flight complete"
```

Now raise the core to 13.4. Do it by **editing `composer.json`** — do not reach
for `composer require`:

```bash
# raise every typo3/cms-* constraint in composer.json from ^12.4 to ^13.4
ddev composer update -W
```

**Expected: it fails.** Read the error properly before you change anything else.

### Questions

1. What is blocking it? It is not TYPO3, and it is not a third-party extension.
   (Look at the package name in "Problem 1".)
2. Fix that, then run `ddev composer update -W` again. Does it get further?
3. `typo3/testing-framework` also has to move. Which major, and how did you find
   out? (Its README has the version matrix. This is the "read the extension's own
   upgrade instructions" moment.)
4. Now try the other way round on a scratch branch:
   `ddev composer require "typo3/cms-core:^13.4" -W`. It fails differently.
   Why is editing `composer.json` + `update -W` the reliable route for a major
   bump, and `require` not?
5. Once you are on 13.4, check what moved that you did not ask for:
   `git diff composer.lock | grep -c '"name"'`.

> **The point:** your dependency graph is one graph. Your own extensions and your
> dev tooling are in it, and either can veto a production upgrade. That is an
> argument for keeping tools in an isolated install — and for `-W`.

## Lab 07 · Rector and Fractor, for real

**Phase: Flight  ·  ~25 min**

Now that the new core is on disk:

```bash
./Build/Scripts/runTests.sh -s rectorFix
./Build/Scripts/runTests.sh -s fractorFix
git diff
```

### Questions

1. Read **every** hunk before you commit. Anything you would not have written
   yourself?
2. Rector output is unformatted. What do you run next?
3. Look at `ext_localconf.php`. Rector changed how the plugin is registered.
   What exactly did it change, and what does that mean for the `tt_content`
   records that already exist in a real database?
4. Rector also created a **new file** you did not ask for. Find it. Open it.
   It contains a `// TODO`. What is it asking you to do, and what happens if you
   ship without doing it?
5. Now run the tests. Something that passed before the refactoring now fails.
   Which test, and why is it the *only* one that could have noticed?

> **The point:** rector's change was correct. It was also incomplete, and it
> silently invalidated every existing content record using that plugin. Code
> migration and data migration are two different jobs — and only a test that
> renders the plugin tells you the second one is missing.

## Lab 08 · Get the pipeline green

**Phase: Flight  ·  ~40 min**

```bash
./Build/Scripts/runTests.sh -s tests
```

Work the failures until both suites are green on 13.4. You will go through
roughly this sequence — track your own numbers as you go:

| Stage                            | Functional suite                                | Exit |
|----------------------------------|-------------------------------------------------|------|
| 12.4, before departure           | 8 tests · 11 assertions · 4 deprecations        | 1    |
| 13.4, straight after the bump    | 8 tests · 2 assertions · **6 errors** · 4 depr. | 2    |
| 13.4, after Rector and Fractor   | 8 tests · 6 assertions · **3 errors** · 1 depr. | 2    |
| 13.4, after the hand work        | **OK — 8 tests · 11 assertions**                | 0    |

### Questions

1. Which failures were **errors** (removed API — the code cannot run) and which
   were **deprecations** (still working, but scheduled)? Two different urgencies,
   and you should treat them differently.
2. Rector cleared three of the six errors. Name the three it could not, and say
   for each one *why* a refactoring tool cannot fix it.
3. One of the remaining failures is not a code problem at all — it is a **data**
   problem. Which one? (See Lab 07, question 3.)
4. Your test fixtures are content records too. What did you have to do to
   `Tests/Functional/Fixtures/tt_content.csv`, and what is the production
   equivalent of that edit?
5. Re-run the Extension Scanner. It reported 13 findings on 12.4. What does it
   report now, and does the difference match what you actually fixed?
6. **Count it up.** How many of the problems you fixed today were reported by
   the tests and by nothing else in the pre-flight phase?

> **The point:** this is the lab the whole workshop is built around. With
> coverage, an upgrade is a list of red tests that you turn green one at a time,
> and you always know how far along you are. Without coverage, it is clicking
> around the site hoping you thought of everything — and the plugin that silently
> stopped rendering is the thing you find out about from an editor, three weeks
> later.

## Lab 09 · Wizards, schema, caches

**Phase: Flight  ·  ~20 min**

```bash
./Build/Scripts/runTests.sh -s upgradeList
ddev exec ./vendor/bin/typo3 upgrade:run
ddev exec ./vendor/bin/typo3 database:updateschema   # note: v13 has this command
ddev exec ./vendor/bin/typo3 cache:flush
```

Then open the backend and the frontend and actually look at them.

### Questions

1. `database:updateschema` did not exist in 12.4. What did you use instead in
   Lab 00, and what does that tell you about scripting an upgrade?
2. Open *Upgrade Wizard* in the backend. Your own wizard from Lab 07 should be
   in the list now. Run it. What did it change in the database?
3. Why is the rest of the list shorter than you expected on this instance?
4. Which of these steps is your V1 — the point after which "undo" means
   "restore the backup"?

---

## Lab 10 · Post-flight

**Phase: Post-Flight  ·  ~30 min**

```bash
./Build/Scripts/runTests.sh -s scan          # again, on the new core
./Build/Scripts/runTests.sh -s tests         # must be green
```

Then, in the backend, *Check TCA Migrations* one more time.

### Questions

1. The scanner now reports something about `TypoScriptFrontendController` that
   it never reported on 12.4. Why now?
1b. Re-run *Check TCA Migrations*. You cleared nine in Lab 03 — how many are
   there now, and where did the extra one come from?
2. That finding is not a problem you have to fix today. What is it, then?
3. Enable the deprecation log (`config/system/additional.php`), click around the
   backend as an editor would, then read the log. Did it find anything static
   analysis did not?
4. Remove the ferry equipment: what in this repo exists *only* because of the
   upgrade and should now go?
5. **Debrief.** What did you predict correctly? What surprised you? Write the
   surprise into `QRH.md` — that file is the deliverable that makes the next
   upgrade cheaper.

---

## Where to go next

- Run the suite against **both** core versions in CI before the next upgrade,
  not during it.
- Add coverage for the code you were most afraid of in Lab 08. That fear was
  data.
- Do Lab 10's debrief for real, at work, after your next upgrade.
