# AGENTS.md: Ally for Moodle

Everything above `## This repo` is identical in moodle-tool_ally, moodle-filter_ally and moodle-report_allylti, on `master` and every supported `MOODLE_*_STABLE` branch. Change it in all of them together. Copilot code review reads only the first 4,000 characters, from the pull request's base branch, so the version rules come first.

## Version rules

Moodle offers any higher `$plugin->version` as an update and refuses to install a lower one over a higher one. A wrong number strands sites, and a later release can't undo it. In review, a break of any rule below is a blocking issue.

| Branch | Moodle | Frozen date |
| --- | --- | --- |
| `MOODLE_502_STABLE` | 5.2 | `20261102` |
| `MOODLE_503_STABLE` | 5.3 | `20261103` |

`MOODLE_501_STABLE` and older are no longer supported: no version bumps, no tags. `master` is not a release line.

1. **Version = frozen date + two digits.** Each release adds 1 to the last two digits: `2026110200`, `2026110201`, up to `99`. Never change the date and never use today's date: that let an older line outrank a newer one (filter_ally issue 109). The version line says so: `// 20261102 = frozen line date YYYYMMDD - do not modify!` This follows https://moodledev.io/docs/apis/commonfiles/version.php.
2. **A new line** gets a date later than every existing line. The first commit on its branch sets version.php to `<date>00` with that comment and `supported`, and sets `requires` to that Moodle version's branch date from core's `public/version.php` (Moodle 5.3 = `2026100500`).
3. **`$plugin->supported = [<line>, <line>]`**. The Marketplace lists the version under these Moodle versions only.
4. **`$plugin->release`** changes with every tagged version (`5.2.3` → `5.2.4`). A version number that was published is never reused.
5. **`$plugin->dependencies`** never exceed what the sibling plugin has released on the same line.
6. **`$plugin->requires`** is the oldest Moodle a build installs on, and the only Moodle check Moodle enforces: `supported` only shows a warning. It stays as it is in a version bump. Change it only in its own pull request that says why.

Before you push a version.php change, run `.github/scripts/check-version-order.sh <line>`. The `Version order` workflow runs the same check on every pull request, and a `FAIL` blocks the merge.

## Branches

- A fix that isn't specific to one Moodle version goes to `master` first. Then `git cherry-pick -x` it onto each supported `MOODLE_*_STABLE` branch: one pull request per branch, each with its own version bump. A fix for one Moodle version only goes straight to that branch.
- Never copy version.php between branches. The date, `supported`, `requires` and dependency minimums differ per line, so a cherry-pick that touches version.php needs fixing by hand.
- filter_ally's CI checks out the branch with the same name in tool_ally and report_allylti, or else that line's `MOODLE_<line>_STABLE`. For a change across repos, push the branches to tool_ally and report_allylti first.

## Releasing

A release publishes to the public Moodle Marketplace and can't be withdrawn. Releases come only from `MOODLE_<line>_STABLE`. An agent asked to release does every step below, one line at a time, and **stops for the person's explicit go-ahead before pushing each tag**.

1. **What ships.** `git fetch origin --tags`, then `git log --oneline <last AY-<line>-R tag>..origin/MOODLE_<line>_STABLE`. Nothing listed: nothing to release. Release a sibling plugin too only when a dependency minimum moves.
2. **Bump** in a pull request into `MOODLE_<line>_STABLE`: `$plugin->version` +1 (rule 1) and `$plugin->release` +1 in its last part (`5.2.3` → `5.2.4`). Nothing else changes in version.php.
3. **Before tagging**, on the merged tip `<sha>`:
   - `.github/scripts/check-version-order.sh <line>` prints `OK`.
   - CI passed on `<sha>`, or `<sha>` has the same tree (`git rev-parse <sha>^{tree}`) as a pull request head whose CI passed. Only filter_ally runs tests in CI.
   - The sibling versions that `dependencies` need are on the Marketplace.
   - The next unused tag number: `git ls-remote --tags origin 'AY-<line>-*'`.
4. **Tag**, after the go-ahead: `git tag AY-<line>-R<n> <sha>`, then `git push origin AY-<line>-R<n>`. `<sha>` must still be the branch tip.
5. **Watch the run**: `gh run list --workflow moodle-release.yml --branch AY-<line>-R<n>`, then `gh run watch <id> --exit-status`. Success is `OK — pushed from MOODLE_<line>_STABLE` from `guard`, and `Response HTTP Status: 201` from the upload, with `version`, `releaseName` and `moodleVersions` matching version.php.
6. **Confirm** about 10 minutes later on `https://marketplace.moodle.com/plugins/<component>/versions`: the line shows `<release> (<version>)`. Only then is it released.

If it fails:
- `guard` fails: the tag is not on a stable branch tip. Delete it (`git push origin :refs/tags/<tag>`, `git tag -d <tag>`) and tag the right commit.
- The upload is not `201`: the log prints the response body. `401` or `403` means `MOODLE_MARKETPLACE_TOKEN` is missing or expired, and a person must replace it.
- Never re-run a job that got `201`, and never delete a published tag.

## This repo: tool_ally

The admin tool that holds **all** Ally configuration and the web services Ally calls. It installs at `public/admin/tool/ally` and does nothing without filter_ally and report_allylti.

### Commands

Run from the Moodle root:

```bash
vendor/bin/phpunit --testsuite tool_ally_testsuite        # this plugin
vendor/bin/phpunit --group ally                           # all three plugins
vendor/bin/phpunit --filter <test> public/admin/tool/ally/tests/<file>_test.php
php public/admin/tool/phpunit/cli/init.php                # after a db/install.xml change
vendor/bin/phpcs --standard=public/admin/tool/ally/phpcs.xml public/admin/tool/ally
cd public/admin/tool/ally && npx grunt amd                # amd/build is committed
php public/admin/tool/ally/cli/autoconfig.php             # create Ally's web service, user, role and token
php public/admin/tool/ally/cli/queryfiles.php --help      # list the files Ally will process
```

This repo has no test CI: filter_ally's CI runs these tests. `.travis.yml` is dead. `Gruntfile.js` loads `../../../Gruntfile.js`, which doesn't exist on Moodle 5.x, so `grunt compile`, `grunt vue` and `grunt decache` fail until that is fixed.

### How it works

- **Pull.** `db/services.php` exposes 17 `tool_ally_*` web services, one class each in `classes/webservice/`, most extending `loggable_external_api`. A new service needs its class, an entry in `db/services.php` (in the `tool_ally` service's function list too) and a test.
- **Push.** Event or hook → `event_handlers` → `content_processor` / `file_processor` / `course_processor` → queue tables → per-minute tasks in `classes/task/` → `push_*_updates` (signed POST with retry) → Ally. The tasks do nothing while `push_config::is_valid()` is false.
- **Content ids** are `component:table:field:id`, with `contenthash = sha1(content)`. `local_content::to_crud()` builds the payload for both pull and push, so treat its shape as a public contract.
- **File selection.** `file_validator` (component and file-area whitelists, author role check), `files_iterator`, and `files_in_use` when the `excludeunused` setting is on.
- **Log viewer.** `logs.php` runs a Vue 2 app from `vue/comps/*/`. Its `dist/` bundles are committed, and Vue is vendored in `vendorjs/`.

### Supporting a new module

`classes/componentsupport/<module>_component.php` teaches the plugin where a module keeps its content and files. The class name is the mapping; there is no registry.

1. Extend `component_base` (or `file_component_base`), implement `component_type()`, and declare `$tablefields`, the tables and fields web services may write.
2. Implement `interfaces\html_content` for rich text. Add `interfaces\annotation_map` for filter_ally's in-page annotations, or `interfaces\content_sub_tables` for child tables, if needed.
3. Reuse `traits\html_content` and `traits\embedded_file_map`.
4. If the module fires its own events, add observers in `db/events.php` and handlers in `classes/event_handlers.php`.
5. Add `tests/components_<module>_component_test.php`.

### Conventions

- A `db/install.xml` change needs a `db/upgrade.php` step ending in `upgrade_plugin_savepoint(true, <version>, 'tool', 'ally')`, with the version bump from rule 1.
- Tests extend `tests/abstract_testcase.php`; shared assertions are in `classes/testing/traits/component_assertions.php`. Tag tests `@group tool_ally` and `@group ally`.
- Component tests for `hsuforum` and `qtype_ddmatch` skip when those plugins are absent. CI no longer installs `qtype_ddmatch`, so its tests always skip there.
