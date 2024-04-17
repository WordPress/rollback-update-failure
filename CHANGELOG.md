[unreleased]

#### 7.2.1 / 2024-04-17
* add check for loopback not working and rollback auto-update for safety

#### 7.2.0 / 2024-03-11
* update kill switch using `method_exists()`
* update for latest PR, cannot include mods to `load.php`

#### 7.1.2 / 2024-03-03
* update kill switch

#### 7.1.1 / 2024-02-14
* update kill switch

#### 7.1.0 / 2023-11-07
* use `( new WP_Upgrader() )->maintenance_mode()` for Upgrader classes, fixes error in `maintenance_mode()` when `$wp_filesystem` not set

#### 7.0.3 / 2023-10-16
* update kill switch
* update for PR
* fix readme.txt, thanks Otto

#### 7.0.2 / 2023-10-12
* just a bump for dot org

#### 7.0.1 / 2023-10-12
* needed to require upgrader classes in main plugin file

#### 7.0.0 / 2023-10-11
* attempt to sync with refactored PR
* simpler replacement with modified upgrader classes
* error logging present

#### 6.3.1 / 2023-10-22
* removed too much stuff

#### 6.3.0 / 2023-10-21
* refactor RAU for merge
* update commit conditional

#### 6.2.2 / 2023-09-13
* re-activate plugins at end of main loop

#### 6.2.1 / 2023-09-02
* add error exception for defining function in main plugin class

#### 6.2.0 / 2023-08-15
* minor email message adjustment
* add default value in email processing for invalid current version
* use `WP_Automatic_Upgrader::after_plugin_theme_update()` for sending email

#### 6.1.0 / 2023-08-12
* add failure email back otherwise no update email is sent

#### 6.0.1 / 2023-08-12
* add back `sleep( 2 )` to prevent potential race condition
* update error exception list

#### 6.0.0 / 2023-08-09
* increase requirements to WP 6.3 and PHP 7.0
* add version check for Rollback part 3
* deactivate/reactivate plugin during auto-update test similar `plugin_sandbox_scrape()` as Core
* add shutdown function
* add method to check if we want an error to pass through, likley caused by calling `include()` on an activated plugin
* log caught error from error handler, exception handler, and shutdown function
* temporary halt to failure email

#### 5.3.3 / 2023-07-16
* remove Reflection in `WP_Rollback_Auto_Update::cron_rollback()` as methods are public

#### 5.3.2 / 2023-07-10
* cleanup

#### 5.3.1 / 2023-06-21
* cleanup email sending

#### 5.3.0 / 2023-05-21
* improved language for email
* ensure `find_core_auto_update()` is available
* put guard for Rollback part 2 not being committed back
* add theme update data to failure email

#### 5.2.0 / 2023-05-09
* restart theme auto-updates
* remove guard for Rollback not being committed
* set Plugin_Upgrader via hook
* cleanup Plugin_Upgrader hook from fatal update

#### 5.1.1 / 2023-05-05
* update readme

#### 5.1.0 / 2023-05-03
* align docblocks with PR
* update for Rollback committed to core
* change `temp-backup` to `upgrade-temp-backup`

#### 5.0.6 / 2023-04-25
* update code logic for creating `temp-backup` dir, thanks @azaozz

#### 5.0.5 / 2023-04-14
* hotfix for no autoload

#### 5.0.4 / 2023-04-14
* update tests
* update GitHub Actions
* ignore vendor directory

#### 5.0.3 / 2023-03-22
* update @since
* update using constant to check version for when `move_dir()` was committed
* update using constant to check version for when `Rollback` was committed
* update for PR compatibility
* developery stuff

#### 5.0.2 / 2023-02-05
* make variables static to retain value during auto-update run

#### 5.0.1 / 2023-02-03
* ensure `move_dir()` called with 3rd parameter as `move_dir($from, $to, true)`

#### 5.0.0 / 2023-02-02
* during `WP_Rollback_Auto_Update::restart_updates` remove shutdown hook for `WP_Upgrader::delete_temp_backup`
* skip second sequential call to `create_backup`
* now require at least WP 6.2-beta1, deactivate if requirements not met
* Faster Updates no longer required as [committed to core](https://core.trac.wordpress.org/changeset/55204)

#### 4.1.2 / 2023-01-25
* update `move_dir()` for new parameter

#### 4.1.1 / 2023-01-20
* ensure specific functions are loaded to check for Faster Updates

#### 4.1.0 / 2023-01-20
* change directory name of rollback to distinguish from update.
* update for `move_dir()` possibly returning `WP_Error`
* fix `sprintf` error
* remove auto-install/activate of Faster Updates

#### 4.0.0 / 2023-01-10
* cast `upgrade_plugins` transient to object, overkill but someone reported an error
* merge Rollback Auto Update
* require [Faster Updates](https://github.com/afragen/faster-updates) for `move_dir()`, auto-install/activate
* no longer requires special filter in `WP_Upgrader::install_package`
* testing only on `update-core.php`

#### 3.3.2 / 2022-12-30
* update for [new filter hook in WP_Upgrader::install_package](https://github.com/WordPress/wordpress-develop/pull/3791)
* update nonce verification for failure simulator

#### 3.3.1 / 2022-10-25
* use `array_unique` when saving simulated failure options
* load failure simulator in `init` hook for WP-CLI

#### 3.3.0 / 2022-10-14
* use `wp-content/temp-backup` and not `wp-content/upgrade/temp-backup` as `WP_Upgrader::unpack_package` deletes contents of `wp-content-upgrade` at each update
* add simulated failure into plugin

#### 3.2.1 / 2022-09-23
* bump auto-deactivation check for WP version

#### 3.2.0 / 2022-09-19
* backup runs on `upgrader_source_selection` from `upgrader_pre_install` to resolve an edge case
* rename functions for action not hook

#### 3.1.1 / 2022-07-31
* update VirtualBox testing URL in readme(s)

#### 3.1.0 / 2022-06-27
* fix to ensure restore functions correctly during bulk update

#### 3.0.0 / 2022-06-14
* remove references to VirtualBox
* add `pre_move_dir` and `post_move_dir` hooks
* use with VirtualBox environment will require a [mu-plugin and a watcher script](https://gist.github.com/costdev/502a2ca52a440e5775e2db970227b9b3) or similar for VirtualBox based environments
* update error messaging in `delete_temp_backup()`

#### 2.2.0 / 2022-05-11
* add initial setup of weekly `wp_delete_temp_updater_backups` cron task, oops

#### 2.1.2 / 2022-05-11
* fix `shutdown` hook in `wp_delete_all_temp_backups()` for plugin namespace, not for PR

#### 2.1.1 / 2022-05-11
* update testing workflows
* fix action hook `wp_delete_temp_updater_backups` for plugin namespace, not for PR

#### 2.1.0 / 2202-04-12
* pass basename of destination to `copy_dir( $skip_list )` to avoid potential endless looping.

#### 2.0.0 / 2022-04-06
* refactor to ease PR back into core by separating out changes into respective files/classes

#### 1.5.0 / 2022-04-04
* remove anonymous callbacks
* add class `$options` for callback functions
* update `is_virtualbox()` for testing
* add testing scaffold

#### 1.4.0 / 2022-04-03
* move kill switch to WP6.1-beta1
* add non-direct filesystem rename variants to `move_dir()`
* bring into alignment with PR

#### 1.3.6 / 2022-03-31
* update credit

#### 1.3.5 / 2022-03-31
* add more Site Health info for runtime environment
* update `move_dir()`
* add `is_virtualbox()`
* remove `WP_RUNTIME_ENVIRONMENT` and `wp_get_runtime_environment()`

#### 1.3.4 / 2022-03-21
* run `restore_temp_backup()` in `shutdown` hook

#### 1.3.3 / 2022-03-18
* add `wp_get_runtime_environment()` to return value of constant `WP_RUNTIME_ENVIRONMENT`
* allowed values are obviously up for discussion
* update to most of current PR

#### 1.3.2 / 2022-02-15
* update to correspond to core patch

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
