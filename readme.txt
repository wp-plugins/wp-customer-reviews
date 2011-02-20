=== WP Customer Reviews ===
Contributors: bompus
Donate link: http://www.gowebsolutions.com/plugins/wp-customer-reviews/
Tags: hreview, microformat, microformats, rdfa, hcard, reviews, testimonials, plugin, google, rating
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 1.1.5

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

= 1.1.3 (02-17-2011) =
* Quick update to fix some issues in admin

= 1.1.2 (02-17-2011) =
* Now using an external stylesheet - makes it easier to modify and tweak the layout
* Restructured and cleaned up some functions
* Force caching plugins to update the page upon plugin upgrade
* Passes as valid HTML5, XHTML Strict, XHTML Transitional
* Improved performance by using better ways of initializing the plugin
* NEW RATING STARS - had to abandon picking your own colors because it looked awful on colored backgrounds.

= 1.1.1 (02-17-2011) =
* Force caching plugins to update the page upon approval/disapproval/deletion of reviews

= 1.1.0 (02-16-2011) =
* Using jQuery.click instead of jQuery.live since some versions of jQuery are not obeying .live (and should)

= 1.0.9 (02-16-2011) =
* Updated star image and added option so you can pick your own star colors
* Updated star rating styles to prevent layout issues with themes
* Updated styling method to give more priority to plugin styles when inside the plugin
* Prevented automatic redirect to settings page when upgrading
* Made "powered by" link use a smaller font size

= 1.0.8 (02-15-2011) =
* Some minor styling tweaks for better compatibility with various themes
* Added a more detailed settings explanation to prevent confusion on plugin activation

= 1.0.7 (02-15-2011) =
* A couple of minor style tweaks for the chosen reviews page layout

= 1.0.6 (02-14-2011) =
* Several major bug fixes - too many to document each one.
* More restrictive CSS styling to prevent interference with themes and other plugins
* Added simple minification of outputted script and style sections
* A flaw was identified and fixed in the handling of outputting the aggregate footer and business hCard.
* The plugin now removes the filter `wpautop` on the page it is used on. This filter was causing WP to inject paragraphs for most line breaks, which broke validation and caused some issues with themes.

= 1.0.5 (02-13-2011) =
* `Selecting a page` to use the plugin on was only returning the last page. It will now display all pages
* `Support Us` will remember your last saved setting, even between upgrades
* `Selecting a page` now supports selecting and working on pages that are `hidden` by other plugins

= 1.0.4 (02-13-2011) =
* `Support Us` is now deselected on upgrades

= 1.0.3 (02-03-2011) =
* Admin email notification for future updates is now optional

= 1.0.2 (02-02-2011) =
* `Support Us` is not selected by default
* Fixed a possible bug when upgrading versions of the plugin

= 1.0.1 (01-30-2011) =
* First public release
* Added many more configuration options
* Added searching capability for all reviews

= 1.0.0 (01-05-2011) =
* New: First Release (private)

== Upgrade Notice ==

= 1.1.5 =
1.1.5 fixes some major bugs and compatibility issues, and adds many new features. It is highly recommended to upgrade.