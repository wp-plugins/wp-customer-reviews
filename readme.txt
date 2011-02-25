=== WP Customer Reviews ===
Contributors: bompus
Donate link: http://www.gowebsolutions.com/plugins/wp-customer-reviews/
Tags: hreview, microformat, microformats, rdfa, hcard, reviews, testimonials, plugin, google, rating, review, review box, seo, business, testimonial, ratings, review widget, widget
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 1.2.2

WP Customer Reviews allows your customers and visitors to leave reviews or testimonials of your services. Reviews are Microformat enabled (hReview).

== Description ==

There are many sites that are crawling for user-generated reviews now, including Google Places and Google Local Search. WP Customer Reviews allows you to setup a specific page on your blog to receive customer testimonials.

* All submissions are moderated, which means that YOU choose which reviews get shown.
* Reviews are displayed to visitors in a friendly format, but search engines see the hReview microformat (and RDFa soon!)
* Multiple anti-spam measures to prevent automated spambots from submitting reviews.
* Provides a configurable `Business hCard`, to help identify all pages of your site as belonging to your business.
* Completely customizable, including which fields to ask for and show.
* Works with caching plugins and all themes.
* Includes an external stylesheet so you can modify it to better fit your theme.
* Reviews can be edited by admin for content and date.
* And much more...

More information at: [**WP Customer Reviews**](http://www.gowebsolutions.com/plugins/wp-customer-reviews/)

== Installation ==

1. Upload contents of compressed file (wp-customer-reviews) to the `/wp-content/plugins/` directory. 
2. Activate the plugin through the `Plugins` menu in WordPress admin.
3. Create a WordPress page to be used specifically for gathering reviews or testimonials.
4. Go into settings for WP Customer Reviews and configure the plugin.

== Screenshots ==

1. Admin Moderation of Comments
2. Admin Options #1
3. Admin Options #2
4. Example of what visitors will see
5. A visitor submitting a review

== Frequently Asked Questions ==
* If you have any feedback, suggestions, questions, or issues, please: [**Visit our support forum**](http://wordpress.org/tags/wp-customer-reviews?forum_id=10)

== Changelog ==

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

= 1.1.5 (02-20-2011) =
* Some minor CSS fixes, cleanups, and added some spacing to fields
* Added options for which fields to ask for (and show) on the reviews page
* Review form is now displayed using tables to make it more appealing and flexible
* Fixing line break formatting of reviews on review page
* Review form is now hidden on default, and opens with a styled button with animation when a user clicks to submit a review
* Added option for choosing to use an h2, h3, or h4 for review titles
* Added pagination to review page with option of # shown per page
* Javascript is now loaded externally to prevent odd issues and allow for easier customizations
* You may now edit date, title, name, and text of reviews in the admin area
* Reviews may now be permanently deleted via the admin area
* Cleaned up some redirects and methods used
* Some additional anti-spam measures are now used.. just because we can
* Refreshing pages will no longer try to repost the review form

= 1.1.4 (02-17-2011) =
* Plugin now actually obeys the options for where to output the hCard and Aggregate Reviews
* Fixed an issue where reviews were not showing up on some themes, due to the order of events
* Some minor code cleanups and internal improvements

== Upgrade Notice ==

= 1.2.2 =
Fixes some major bugs and compatibility issues, and adds many new features. It is highly recommended to upgrade.