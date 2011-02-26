=== WP Customer Reviews ===
Contributors: bompus
Donate link: http://www.gowebsolutions.com/plugins/wp-customer-reviews/
Tags: hreview, microformat, microformats, rdfa, hcard, reviews, testimonials, plugin, google, rating, review, review box, seo, business, testimonial, ratings, review widget, widget, hproduct, product, snippet, snippets
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 2.0.0

WP Customer Reviews allows your customers and visitors to leave reviews or testimonials of your services. Reviews are Microformat enabled (hReview).

== Description ==

There are many sites that are crawling for user-generated reviews now, including Google Places and Google Local Search. WP Customer Reviews allows you to setup a specific page on your blog to receive customer testimonials for your business/service OR to write reviews about multiple products (using multiple pages).

* All submissions are moderated, which means that YOU choose which reviews get shown.
* Reviews are displayed to visitors in a friendly format, but search engines see the hReview microformat (and RDFa soon!)
* Multiple anti-spam measures to prevent automated spambots from submitting reviews.
* Provides a configurable `Business hCard`, to help identify all pages of your site as belonging to your business.
* Completely customizable, including which fields to ask for, require, and show.
* Works with caching plugins and all themes.
* Includes an external stylesheet so you can modify it to better fit your theme.
* Reviews can be edited by admin for content and date.
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

= 2.0.0 (02-26-2011) =
* [New] Added support for custom fields.
* [New] Now supports using the plugin on more than one page.
* [New] Now supports both "Business" and "Product" hReview formats.
* [New] The plugin is now even faster since administrative functions are only included as needed.
* [Update] Better format of the aggregate review format. This will make review pages ouput with a considerably smaller size.
* [Update] Some better methods of validation in the administrative area. This automatically prevents you from choosing combinations that do not make any sense (ask/require/show fields).
* [Update] The plugin has been almost completely rewritten for the inclusion of the new features and for better performance. It was incredibly quick before, and now it is even faster.
* [Fix] Numerous bug fixes. Too many to mention. Many were very minor and probably would never have been an issue, but you can't be too safe.

= 1.2.4 (02-25-2011) =
* Quick fix to the epic failure of the previous update. Everything should be back to normal now.

= 1.2.3 (02-25-2011) =
* Updated the method we use for appending the_content. We now use add_action instead of add_filter to try to solve some theme problems.

= 1.2.2 (02-25-2011) =
* Updated some CSS to avoid some more theme issues.

= 1.2.1 (02-25-2011) =
* The last update caused a bug where the form would never be submitted. This has been corrected.

= 1.2.0 (02-25-2011) =
* Fixed an issue in the admin area where line breaks would appear to be lost when editing reviews.

= 1.1.9 (02-24-2011) =
* Restructured some Javascript validation to prevent an odd jQuery conflict

= 1.1.8 (02-23-2011) =
* Updated plugin to not use sessions at all to track error/status messages
* Plugin will now automatically detect some theme/plugin compatibility issues and attempt workarounds
* Updating CSS for error/thank you message container

= 1.1.7 (02-20-2011) =
* An error was fixed in email validation
* A few minor cosmetic bugs were fixed
* Trying to edit a review to be 5 star ratings would always end up with 4 stars
* Editing ratings will now show the new star ratings image after editing.

= 1.1.6 (02-20-2011) =
* "Powered by" link now properly obeys the enabled/disabled setting
* When checking required fields in settings, the "ask for" fields will now automatically be checked
* "Show name" now properly obeys the enabled/disabled settings. For hReview, it will show up as Anonymous.

== Upgrade Notice ==

= 2.0.0 =
Fixes some major bugs and compatibility issues, and adds many new features. It is highly recommended to upgrade.