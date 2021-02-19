# Feature Plugin: Rollback Update Failure

This feature plugin is an offshoot of [Matt's 9 Projects for 2019](https://make.wordpress.org/core/2018/12/08/9-priorities-for-2019/). Specifically it's a follow-up to [auto-updates for plugins and themes](https://make.wordpress.org/core/2020/02/26/feature-plugin-wp-auto-updates/). Our goal is to provide a safety mechanism of sorts should an update, including auto-updates, fail potentially leaving the user's site in an unstable state.

This is a feature plugin based on the [PR](https://github.com/WordPress/wordpress-develop/pull/860) for [#51857](https://core.trac.wordpress.org/ticket/51857). We are working towards inclusion in WordPress 5.8. The general overview is to provide a mechanism whereby a failed plugin or theme update does not leave the site in an unstable state. This part of the project is **not** about ensuring that a **successful** update does not cause any issues.

Some of the issues of failed updates include:

* Having a plugin folder content be deleted and the plugin no longer active.
* Having a plugin not completely update and result in a PHP fatal message or WSOD

While these are not the only results of failed updates, they seem to consistute the majority of reported issues.

The assumption is that most of the errors in large plugins/themes occur during the `copy_dir()` part of `WP_Upgrader::install_package()`. Trac ticket [#52342](https://core.trac.wordpress.org/ticket/52342) brought more error reporting to `copy_dir()` and Trac ticket [#52831](https://core.trac.wordpress.org/ticket/52381) provides a filter hook in order to process the rollback in the event of a plugin/theme update failure. As of WordPress 5.7-beta1 both of these tickets are in core.

It is during the `WP_Upgrader::install_package()` that the currently installed plugin/theme is deleted in anticipation of copying the new update into that location. Having an empty plugin/theme folder or an incompletely copied update seems to be the most common issue.

[Rollback Update Failure Feature Plugin](https://wordpress.org/plugins/rollback-update-failure) is available for feedback and testing. Contributions from the WordPress community are welcome on [the plugin's GitHub repository](https://github.com/afragen/rollback-update-failure).

## Next Steps

The [Rollback Update Failure feature plugin](https://wordpress.org/plugins/rollback-update-failure) is a early step towards inclusion in WordPress Core. We need your help. Simply installing and activating the plugin will help test whether or not the additional server IO processes may cause issue with resource starved shared hosting.

## Testing

There was [much discussion](https://wordpress.slack.com/archives/CULBN711P/p1609968242405800) regarding the thought that adding additional IO processes for the zip and unzip process could result in server timeout issues on resource starved shared hosts. Activating the feature plugin will result in the creation of a ZIP file of the installed plugin/theme being updated every time an update is performed. The unzip only occurs during testing or a `WP_Error` resulting from `WP_Upgrader::install_package()`. Any issues would only happen during a plugin or theme update.

For the sake of testing assume any server timeout occurring during the update process might be releated to the additional IO processes creating the zipfile. Please report these in [GitHub Issues](https://github.com/afragen/rollback-update-failure/issues) and report your server details. ( Host, RAM, OS, etc. )

There will be messaging in the event of an error and successful or unsuccessful rollback.

To simulate a failure, use the filter `add_filter( 'rollback_update_testing', '__return_true' );`

Alternatively you can install the [Rollback Update Testing](https://gist.github.com/afragen/80b68a6c8826ab37025b05d4519bb4bf) plugin, activating it as needed. If you have [GitHub Updater](https://github.com/afragen/github-updater) installed, you can easily install this **Gist** from the **Install Plugin** tab. Select **Gist** as the **Remote Repository Host**.
