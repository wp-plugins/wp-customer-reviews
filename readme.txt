=== WP Customer Reviews ===
Contributors: bompus
Donate link: http://www.gowebsolutions.com/plugins/wp-customer-reviews/
Tags: hreview, microformat, microformats, rdfa, hcard, reviews, testimonials, plugin, google, rating, review, review box, seo, business, testimonial, ratings, review widget, widget, hproduct, product, snippet, snippets
Requires at least: 2.8.6
Tested up to: 3.3
Stable tag: trunk

WP Customer Reviews allows your customers and visitors to leave reviews or testimonials of your services. Reviews are Microformat enabled (hReview).

== Description ==

There are many sites that are crawling for user-generated reviews now, including Google Places and Google Local Search. WP Customer Reviews allows you to setup a specific page on your blog to receive customer testimonials for your business/service OR to write reviews about multiple products (using multiple pages).

* WP Multisite and Multiuser (WPMU / WPMS / Wordpress MU) compatible.
* All submissions are moderated, which means that YOU choose which reviews get shown.
* Reviews are displayed to visitors in a friendly format, but search engines see the hReview microformat (and RDFa soon!)
* Multiple anti-spam measures to prevent automated spambots from submitting reviews.
* Provides a configurable `Business hCard`, to help identify all pages of your site as belonging to your business.
* Completely customizable, including which fields to ask for, require, and show.
* Works with caching plugins and all themes.
* Includes an external stylesheet so you can modify it to better fit your theme.
* Reviews can be edited by admin for content and date.
* Admin responses can be made and shown under each review.
* Support for adding your own custom fields with one click.
* The plugin can be used on more than one page.
* Supports both `Business` and `Product` hReview types.
* Shows aggregate reviews microformat (`hReview-aggregate`).
* Fast and lightweight, even including the star rating image. This plugin will not slow down your blog.
* Validates as valid XHTML 1.1 (W3C) and valid Microformats (Rich Snippets Testing Tool).
* And much more...

This is a community-driven , donation-funded plugin. Almost every new feature that has been added was due to the generous support and suggestions of our users. If you have a suggestion or question, do not hesitate to ask in our forum.

More information at: [**WP Customer Reviews**](http://www.gowebsolutions.com/plugins/wp-customer-reviews/)

== Installation ==

1. Upload contents of compressed file (wp-customer-reviews) to the `/wp-content/plugins/` directory. 
2. Activate the plugin through the `Plugins` menu in WordPress admin.
3. Create a WordPress page to be used specifically for gathering reviews or testimonials.
4. Go into settings for WP Customer Reviews and configure the plugin.

== Screenshots ==

1. Admin Moderation of Comments (v1.2.4)
2. Admin Options #1 (v1.2.4)
3. Admin Options #2 (v1.2.4)
4. Example of what visitors will see (v1.2.4)
5. A visitor submitting a review (v1.2.4)

== Frequently Asked Questions ==
* If you have any feedback, suggestions, questions, or issues, please: [**Visit our support forum**](http://wordpress.org/tags/wp-customer-reviews?forum_id=10)

== Changelog ==

= 2.3.8 =
* [Fix] 2.3.7 had introduced a redirect loop when loaded on a new page with no reviews

= 2.3.7 =
* [Fix] Admin - Using the enter key with in-place editing of reviews will no longer refresh the page
* [Fix] A recent update was forcing review pages to always jump to the review form. This has been corrected
* [Fix] Several refactorings to increase compatibility with themes and other plugins
* [Fix] Plugin now works without causing any NOTICE warnings if E_NOTICE is enabled in PHP configuration
* [Update] Shortcode implementation has been updated. Any pages using shortcodes will need to be updated to use the new format
* [Update] The plugin should now handle activation and upgrades better
* [Update] MANY bug fixes, cleanups, performance fixes, and internal changes

= 2.3.6 =
* [Fix] 2.3.4 had also broken pagination. This should now be working again.

= 2.3.5 =
* [Fix] The previous release had broken some of the styles, since it output the reviews outside of their expected container.

= 2.3.4 =
* [Fix] Theme compatibility updates
* [Fix] Last characters of the last custom field was being cut off
* [Update] Several internal cleanups and refactorings
* [Update] Additional custom fields have been added for a total of 6
* [Update] CSS and JS are back to only being loaded when on an enabled page/post
* [Update] Cleaning up custom field display for better separation on page display
* [Update] Added setting to enable plugin for all future posts/pages
* [Update] Added buttons to enable/disable plugin for all existing posts/pages
* [Update] Admin can now respond to reviews. (Thank you Benjamin W for a great user contribution)
* [Update] Beta testing - Shortcode has been added for displaying the reviews/form anywhere in any page
* [Update] Beta testing - Shortcode has been added for displaying review contents from any page in any other page

= 2.3.3 =
* [Update] Forgot to bump internal version number

= 2.3.2 =
* [Update] Bumping version number to force WP Plugin Directory to update

= 2.3.1 =
* [Update] Verified compatibility with Wordpress 3.3

= 2.3.0 =
* [Fix] Updated methods to include required Javascript and CSS files. This should solve some compatibility issues with some themes.

== Upgrade Notice ==

= 2.3.8 =
Several fixes for compatibility with themes and plugins
