=== Kashiwazaki SEO Old Slug Manager ===
Contributors: Tsuyoshi Kashiwazaki
Tags: seo, redirect, 301, slug, permalink, manager
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin to manage old slugs, allowing you to edit, delete, and disable redirects for them from the WordPress admin panel.

== Description ==

This plugin provides a comprehensive management screen for WordPress's old slugs (`_wp_old_slug`). It allows you to:

*   **View a list of all old slugs** stored in your database.
*   **Edit old slugs** directly.
*   **Edit the current slug (permalink)** of the associated post.
*   **Delete individual old slugs** with a confirmation.
*   **Disable 301 redirects** for specific old slugs, forcing them to resolve without redirecting (or result in a 404 if the slug is no longer active).
*   Sort the list by Post ID, Old Slug, Current Slug, or Modification Date.

This is a powerful tool for developers and SEO professionals who need fine-grained control over their site's URL structure and redirect behavior.

== Installation ==

1.  Upload the `kashiwazaki-seo-old-slug-manager` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  A new menu item, "Old Slug Manager," will appear in your WordPress admin sidebar.

== Frequently Asked Questions ==

= Is it safe to delete old slugs? =

**Use caution.** Deleting an old slug permanently removes the automatic 301 redirect from the old URL to the new one. This can negatively impact your SEO and break external links or user bookmarks. Only delete a slug if you are certain it is no longer needed or if you intentionally want to make the old URL inactive (result in a 404).

= What happens when I edit a "Current Slug"? =

Editing a "Current Slug" changes the post's actual permalink (`post_name`). When you do this, WordPress will automatically save the *previous* current slug as a *new* old slug to maintain a redirect. Be aware that this action will create a new entry in the old slug list.

== Screenshots ==

1. The main management screen showing the list of old and current slugs with editing, deletion, and redirect-disabling options.

== Changelog ==

= 1.0.1 =
*   Improved: Transfer URL editing now changes the binding target post.
*   Added: HTTP status check feature (displays 200/3xx/4xx).
*   Changed: Status badge colors (301 Redirect = yellow, Transfer Stopped = gray).
*   Changed: New slug creation form now uses slug input instead of post ID.
*   Improved: README.md and help tab documentation.
*   Added: LICENSE file (GPL-2.0).

= 1.0.0 =
*   Initial release.
*   Feature: Disable old slug and canonical redirects for specified URL patterns.
*   Feature: Admin screen to select old slugs from a list to disable their redirects.