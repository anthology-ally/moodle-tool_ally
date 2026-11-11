# Changelog

Release notes for this Moodle line, newest first. The release workflow publishes the
first `## ` section to the Moodle Marketplace, so every version bump PR adds one on top:
`## <release> (<version>)`, with both values copied from version.php.

Write for a Moodle admin deciding whether to upgrade: what changed, and whether anything
needs doing. Example, indented so the workflow ignores it (write yours without the indent):

    ## X.Y.Z (YYYYMMDDXX)

    ### Fixed
    - Ally feedback icons on images inside tables sit next to the image again.

    ### Changed
    - <one line on behaviour an admin or teacher will notice>

    ### Upgrade notes
    - No database changes and no new settings.

## 5.0.5 (2026103026)

### Fixed
- Fixed an issue in the Instructor Feedback where removing a GIF that could cause seizures
  removed only its accessibility score. The GIF is now also removed from the course content,
  so students no longer see it.

### Upgrade notes
- No database changes and no new settings.
