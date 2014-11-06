=== Curator ===
Contributors:      aaronholbrook, ghosttoast, 10up
Tags:              curation, query, cpt, custom post types, order, sort
Requires at least: 4.0
Tested up to:      4.1-alpha
Stable tag:        0.2.2
License:           MIT
License URI:       http://opensource.org/licenses/MIT

Select specific posts from across multiple post types to combine together and control the ordering.

== Description ==

If you've ever needed to create a query that pulls in multiple post types, posts and post formats but still allow for the control of the order of those items, you'll find WordPress falls a bit short.

Curator let's you specify which post types should be curated and then provides an interface for ordering those.

== Installation ==

= Manual Installation =

1. Upload the entire `/curator` directory to the `/wp-content/plugins/` directory.
2. Activate Curator through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

= 0.2.2 =
* Add cur_is_featured() function to detect if a post is featured
* Add tests for featuring a post and using cur_is_featured()
* Raise WP version requirement to 4.0 because the index_key parameter wasn't added to wp_list_pluck until 4.0.0

= 0.2.1 =
* Add tests for curating and uncurating posts

= 0.2.0 =
* Featurer module
* Pinner module

= 0.1.0 =
* First release

== Upgrade Notice ==

= 0.1.0 =
First Release