=== WP Customer Reviews ===
Contributors: bompus
Donate link: http://www.gowebsolutions.com/plugins/wp-customer-reviews/
Tags: hreview, microformat, microformats, rdfa, hcard, reviews, testimonials, plugin, google, rating, review, review box, seo, business, testimonial, ratings, review widget, widget, hproduct, product, snippet, snippets
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 2.1.1

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

= 2.1.2 () =
* [Cleanup] Updated some activation/deactivation hooks.

= 2.1.1 (03-01-2011) =
* [Fix] Updated "thank you" message to try to prevent wordpress escaping quotes.

= 2.1.0 (03-01-2011) =
* [Fix] Quick fix to correctly clear "thank you" message upon future visits to the reviews page.

= 2.0.9 (03-01-2011) =
* [Update] Some themes were not firing hooks to enqueue scripts and process the form. We are now enqueuing using a different hook.
* [Update] Restructured the method in which some actions are called to try an eliminate future theme issues.

= 2.0.8 (02-29-2011) =
* [Fix] In place editing was not wrapping text automatically due to the 2.0.7 fix.

= 2.0.7 (02-28-2011) =
* [Fix] IE 8 was having issues with inplace editing in the admin area. A workaround has been made for all browsers.

= 2.0.6 (02-28-2011) =
* [Fix] Server-side form validation has also been corrected.

= 2.0.5 (02-28-2011) =
* [Fix] 2.0.3 had prevented some form validation from triggering.

= 2.0.4 (02-28-2011) =
* [Update] Plugin upgrade was failing for some blogs. This has been fixed.

= 2.0.3 (02-28-2011) =
* [Fix] Additional minification was added to prevent automatic WordPress linebreaks from occuring within the plugin output.
* [Update] Plugin activation was failing for some blogs. This has been fixed.

= 2.0.2 (02-28-2011) =
* [Fix] Some CSS button workarounds for more themes.
* [Fix] Some validation has been relaxed to prevent a possible bug.

= 2.0.1 (02-26-2011) =
* [Fix] 2.0.0 had introduced a blocking error in plugin activation.

== Upgrade Notice ==

= 2.1.1 =
Fixes some major bugs and compatibility issues, and adds many new features. It is highly recommended to upgrade.