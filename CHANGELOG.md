[unreleased]

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
