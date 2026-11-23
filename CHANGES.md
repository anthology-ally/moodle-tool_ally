# Changelog

Release notes for this Moodle line, newest first. The release workflow publishes the
first `## ` section to the Moodle Marketplace, so every version bump PR adds one on top:
`## <release> (<version>)`, with both values copied from version.php.

Write for a Moodle admin deciding whether to upgrade: what changed, and whether anything
needs doing. Example, indented so the workflow ignores it (write yours without the indent):

    ## 5.2.3 (2026110200)

    ### Fixed
    - AB#nnnnnn: Ally feedback icons on images inside tables sit next to the image again.

    ### Changed
    - AB#nnnnnn: <one line on behaviour an admin or teacher will notice>

    ### Upgrade notes
    - No database changes and no new settings.

## 5.3.1 (2026110301)

### Added
- Ally's configuration link and the institution and course reports can launch over LTI 1.3. Each one uses LTI 1.3 once its Ally tool is registered in Moodle (Add LTI Advantage) and active; until then it launches over LTI 1.1 with the key and secret, as before.
- A new LTI 1.3 section in the Ally settings lists the registration URL for each Ally tool and whether it is registered.

### Upgrade notes
- No database changes and no new settings. LTI 1.3 is optional: nothing changes until an admin registers the Ally tools.

## 5.3.0 (2026110300)

### Added
- Support for Moodle 5.3. This version requires Moodle 5.3.

### Fixed
- Moodle 5.3 removed functions that Ally used to list a site's courses and to connect a new site. Ally now uses their replacements, so both work on Moodle 5.3.

### Upgrade notes
- Requires Moodle 5.3. No database changes and no new settings.
