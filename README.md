![Rollback Update Failure](./.wordpress-org/icon.svg)

# Rollback Update Failure

* Plugin Name: Rollback Update Failure
* Contributors: afragen, aristath
* Tags: feature plugin, update, failure, auto-update
* License: MIT
* Requires PHP: 5.6
* Requires at least: 5.2
* Stable Tag: main

Feature plugin for testing automatic rollback of a plugin or theme update failure.

## Description

This is a feature plugin based on the [PR](https://github.com/WordPress/wordpress-develop/pull/1492) for [#51857](https://core.trac.wordpress.org/ticket/51857).

* When updating a plugin/theme, the old version of the plugin/theme gets moved to a `wp-content/upgrade/temp-backup/plugins/PLUGINNAME` or `wp-content/upgrade/temp-backup/themes/THEMENAME` folder. The reason we chose to **move** instead of **zip**, is because zipping/unzipping are very resources-intensive processes, and would increase the risk on low-end, shared hosts. Moving on the other hand is performed instantly and won't be a bottleneck.
* If the update fails, then the "backup" we kept in the `upgrade/temp-backup` folder gets restored to its original location
* If the update succeeds, then the "backup" is deleted
* 2 new checks were added in the site-health screen:
  * Check to make sure that the rollbacks folder is writable.
  * Check there is enough disk-space available to safely perform updates.

To avoid confusion: The "temp-backup" folder will NOT be used to "roll-back" a plugin to a previous version after an update. This folder will simply contain a **transient backup** of the previous version of a plugins/themes getting updated, and as soon as the update process finishes, the folder will be empty.

This plugin will automatically deactivate itself once the feature has been committed to core.

There is a change to `WP_Upgrader::install_package()` that can't be implemented in the plugin. :sad:

## Testing

* If the `wp-content/temp-backup` folder is not writable, there should be an error in the site-health screen.
* If the server has less than 20MB available, there should be an error in the site-health screen that updates may fail.
* If the server has less than 100MB, it should be a notice that disk space is running low.
* When updating a plugin, you should be able to see the old plugin in the `wp-content/upgrade/temp-backup/plugins/PLUGINNAME` folder. The same should apply for themes. Since updates sometimes run fast and we may miss the folder creation during testing, you can simulate an update failure to demonstrate. This will return early and skip deleting the backup on update-success.
* When a plugin update fails, the previous version should be restored. To test that, change the version of a plugin to a previous number, run the update, and on fail the previous version (the one where you changed the version number) should still be installed on the site. To simulate an update failure and confirm this works, you can use the snippet below:

```php
add_filter( 'upgrader_install_package_result', function() {
   return new WP_Error( 'simulated_error', 'Simulated Error' );
});
```

Alternatively you can install the [Rollback Update Testing](https://gist.github.com/afragen/80b68a6c8826ab37025b05d4519bb4bf) plugin, activating it as needed.

## Reporting

Please submit [issues](https://github.com/afragen/rollback-update-failure/issues) and [PRs](https://github.com/afragen/rollback-update-failure/pulls) to GitHub.

Logo from a meme generator. [Original artwork](http://hyperboleandahalf.blogspot.com/2010/06/this-is-why-ill-never-be-adult.html) by Allie Brosh.

## Changelog

Please see the Github repository: [CHANGELOG.md](https://github.com/afragen/rollback-update-failure/blob/main/CHANGELOG.md).
