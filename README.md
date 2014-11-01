Curator [![Build Status](https://travis-ci.org/10up/curator.svg?branch=master)](https://travis-ci.org/10up/curator) [![Test Coverage](https://codeclimate.com/github/10up/curator/badges/coverage.svg)](https://codeclimate.com/github/10up/curator) [![Code Climate](https://codeclimate.com/github/10up/curator/badges/gpa.svg)](https://codeclimate.com/github/10up/curator)
=======

Select specific posts from across multiple post types to combine together and control the ordering.

## Background & Purpose

If you've ever needed to create a query that pulls in multiple post types, posts and post formats but still allow for the control of the order of those items, you'll find WordPress falls a bit short.

Curator let's you specify which post types should be curated and then provides an interface for ordering those.

## Requirements

For this plugin to be effective it should be paired with the [Simple Page Ordering](https://wordpress.org/plugins/simple-page-ordering/) plugin. This will create an easy to use ordering/sorting system for your curated items.

## Configuration

Specify which post types should be curated using the filter ```cur_set_post_types```.

For example, in your theme or plugin you can add the following filter:

```php
// Add our post types to include in the curator plugin
add_filter( 'cur_set_post_types', function( $post_types ) {
	return array(
		'post',
		'page',
		'custom-post-type-slug',
	);
} );
```

In addition to allowing items to be curated, Curator also comes with two additional modules: Featurer and Pinner. These are disabled by default. To enable these two features, use the ```cur_modules``` filter to pass which modules should be enabled/disabled:

```php
// Enable curation, featuring, and pinning of items in the curation plugin
add_filter( 'cur_modules', function( $modules ) {
	$modules['curator']['enabled'] = true;
	$modules['featurer']['enabled'] = true;
	$modules['pinner']['enabled'] = true;

	return $modules;
} );
```

## Usage

### Curating

When editing a post, you will see the option to 'Curate Item' in the publish box. To curate an item, simply check that box and it will be curated after saving of the current post.

![The control for curating items lives in the Publish box of posts](/screenshots/publish-box.png?raw=true "The control for curating items lives in the Publish box of posts")

To manage the order of the curated items, click on the ```Curator``` menu icon:

![The Curator menu item lives below the Dashboard menu item](/screenshots/curator-menu-item.png?raw=true "Curator Menu Item sits below the Dashboard")

To re-arrange items, ensure that you have already installed the [Simple Page Ordering](https://wordpress.org/plugins/simple-page-ordering/) plugin and simply drag and drop items.

![Curated items, ready for ordering](/screenshots/curated-item-list.png?raw=true "List of curated items")

### Uncurating

To uncurate an item, simply uncheck the Curate Item checkbox in the original post, or click Trash in the curator.

### Querying

Curator works seamlessly to provide a full WP_Query object of original post objects regardless of their post type of origin. Simply pass ```cur-curator``` as the ```post_type``` parameter for a WP_Query.

```php
$args = array(
	'post_type' => 'cur-curator',
);

$curated_posts = new WP_Query( $args );
```

Load up your loop and interact with the posts normally:  

```php
if ( $curated_posts->have_posts() ) : while ( $curated_posts->have_posts() ) : $curated_posts->the_post();

	// Use normal WP methods for retrieving post content, meta, etc
	the_title();

	the_content();
endwhile; endif;
```

### Helper Functions

You can use the function ```cur_is_featured()``` either within the loop or outside of the loop (if you pass it a post's ID) to determine if the curated post is featured or not.

Please ensure that you've enabled the featurer module first before attempting to use it, or you will not be able to feature any items or detect if items are featured.

```php
$args = array(
	'post_type' => 'cur-curator',
);

$curated_posts = new WP_Query( $args );

if ( $curated_posts->have_posts() ) : while ( $curated_posts->have_posts() ) : $curated_posts->the_post();

	// Set our default to not be featured
	$featured = false;

	// Ensure this function exists, if you don't wrap the function call and then deactivate the plugin you will cause a fatal error in your installation
	if ( function_exists( `cur_is_featured` ) ) {
		$featured = cur_is_featured();
	}

endwhile; endif;
```
