[unreleased]

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
