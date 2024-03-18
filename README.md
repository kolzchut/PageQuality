# PageQuality
A MediaWiki extension to monitor and improve Page Quality, by several pre-defined metrics, such as:
- Line length
- Paragraph length
- Number of items in a list
- Etc. (TBD: make this list comprehensive)

## Special pages
- Special:PageQuality: main dashboard
- Special:PageQuality/reports: detailed reports
- Special:PageQuality/history: allows comparing progress over time
- Special:PageQuality/settings: for changing various metrics.

## Dependencies
This extension directly embeds Bootstrap 3's styles for badge, panels and list-groups,
which may cause issues with skins that use Bootstrap.

## Excluding elements on the page
Elements with the CSS class `.pagequality-ignore` will be excluded from the audits. 

## Settings

## Permissions
- "viewpagequality" is required to watch the list of issues and use the API.
  By default, this is applied to everyone.
- "configpagequality" is required to edit the settings of this extension.
  By default, this is applied to sysops.
- The extension also defines the group "pagequality-admin", with the "configpagequality" permission.

## Todo
- Lint the code
- Scope styles imported from Bootstrap or replace them with MediaWiki's native elements
- Perhaps: Make the issues' sidebar look and behave like Google Docs' "Version history" drawer
