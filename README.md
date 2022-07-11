# PageQuality
A MediaWiki extension to monitor and improve Page Quality

## Dependencies
This extension currently depends on Bootstrap for styling. If your skin does not load Bootstrap,
it is recommended to install the [Bootstrap extension](https://www.mediawiki.org/wiki/Extension:Bootstrap).

## Excluding elements on the page
Elements with the CSS class `.pagequality-ignore` will be excluded from the audits. 

## Settings

## Permissions
- "viewpagequality" is required to watch the list of issues and use the API.
- "configpagequality" is required to edit the settings of this extension.

## Todo
- Lint the code
- Remove dependency on Bootstrap styles - either embed the styles directly or use MW/OOUI styles.
- Perhaps: Make the issues' sidebar look and behave like Google Docs' "Version history" drawer
