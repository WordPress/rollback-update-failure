# Rollback Update Failure

Plugin Name: Rollback Update Failure
Contributors: afragen, aristath
Tags: feature plugin, update, failure
License: MIT
Requires PHP: 5.6
Requires at least: 5.2
Tested up to: 5.9
Stable Tag: 1.3.1

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

## Testing

* If the `wp-content/temp-backup` folder is not writable, there should be an error in the site-health screen.
* If the server has less than 20MB available, there should be an error in the site-health screen that updates may fail.
* If the server has less than 100MB, it should be a notice that disk space is running low.
* When updating a plugin, you should be able to see the old plugin in the `wp-content/upgrade/temp-backup/plugins/PLUGINNAME` folder. The same should apply for themes. Since updates sometimes run fast and we may miss the folder creation during testing, you can simulate an update failure to demonstrate. This will return early and skip deleting the backup on update-success.
* When a plugin update fails, the previous version should be restored. To test that, change the version of a plugin to a previous number, run the update, and on fail the previous version (the one where you changed the version number) should still be installed on the site. To simulate an update failure and confirm this works, you can use the snippet below:

<pre><code>
    add_filter( 'upgrader_install_package_result', function() {
        return new WP_Error( 'simulated_error', 'Simulated Error' );
    });
</code></pre>

Alternatively you can install the [Rollback Update Testing](https://gist.github.com/afragen/80b68a6c8826ab37025b05d4519bb4bf) plugin, activating it as needed.

## Reporting

Please submit [issues](https://github.com/afragen/rollback-update-failure/issues) and [PRs](https://github.com/afragen/rollback-update-failure/pulls) to GitHub.

Logo from a meme generator. [Original artwork](http://hyperboleandahalf.blogspot.com/2010/06/this-is-why-ill-never-be-adult.html) by Allie Brosh.

## Changelog

Please see the Github repository: [CHANGELOG.md](https://github.com/afragen/rollback-update-failure/blob/main/CHANGELOG.md).

#### 1.3.1 / 2022-01-19
* add logo credit, Logo from a meme generator. [Original artwork](http://hyperboleandahalf.blogspot.com/2010/06/this-is-why-ill-never-be-adult.html) by Allie Brosh.
* remove `(int)` casting for `disk_free_space()`

#### 1.3.0 / 2021-01-12
* introduce `is_virtual_box()` to get whether running in VirtualBox, requires `define( 'ENV_VB', true )` or `genenv( 'WP_ENV_VB' )` evaluating to true
* skips `rename()` as VirtualBox gets borked when using `rename()`

#### 1.2.0 / 2021-12-17
* updated for more parity with planned code
* updated version check for revert
* update to use `move_dir()` instead of `$wp_filesystem->move()`

#### 1.1.3 / 2021-09-17
* update version check

#### 1.1.1 / 2021-09-07
* update check for `disk_free_space()`

#### 1.1.0 / 2021-09-01
* automatically deactivate plugin after feature committed to core, currently set to `5.9-beta1`
* check for disabled function `disk_free_space()` and degrade gracefully

#### 1.0.0 / 2021-08-30
* updated to be on par with [PR #1492](https://github.com/WordPress/wordpress-develop/pull/1492), thanks @aristah
* original zip rollback is now branch [zip-rollback](https://github.com/WordPress/rollback-update-failure/tree/zip-rollback)

#### 0.5.3 / 2021-07-01
* add @10up GitHub Actions integration for WordPress SVN

#### 0.5.2 / 2021-06-10
* exit early if `$hook_extra` is empty

#### 0.5.1 / 2021-03-15
* update error message for installation not update

#### 0.5.0 / 2021-02-10
* initial commit
* use simpler hook for `extract_rollback`
* update for `upgrader_install_package_result` filter and parameters passed
* add text domain
* update error message display
* added filter `rollback_update_testing` to simulate a failure.
* override filter if there's already a WP_Error
