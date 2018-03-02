=== Related Posts for WordPress ===
Contributors: never5, barrykooij, hchouhan
Donate link: http://www.barrykooij.com/donate/
Tags: related posts for wordpress, related posts for wp, simple related posts, easy related posts, related posts, related post, related, relations, internal links, seo, bounce rate
Requires at least: 3.6
Tested up to: 4.9.4
Stable tag: 2.0.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Display related posts without slowing down your website! Link all your existing content with only 1 click, get related posts for all your posts today!

== Description ==

= Related Posts for WordPress =

Related Posts for WordPress offers you the ability to link related posts to each other with just 1 click!

Our installation wizard will do all the hard work for you, simply activate the plugin, set the amount of posts that should relate and press the button. Related Posts for WordPress will do the rest. Relating posts in WordPress has never been this easy!

> #### Related Posts for WordPress Premium
> There's an even better version of this plugin that comes with the following extra features:<br />
>
> - Full Styling Control With Our Configurator<br />
> - Cross Custom Post Type Support<br />
> - Overwritable templates<br />
> - Custom Taxonomy Support<br />
> - Adjustable Weights<br />
> - WordPress Network / Multisite support<br />
> - Keep manually created links<br />
> - Priority Email Support<br />
>
> [More information](https://www.relatedpostsforwp.com/features/?utm_source=wp-plugin-repo&utm_medium=link&utm_campaign=after-features-info-link) | [Upgrade >>](https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=wp-plugin-repo&utm_medium=link&utm_campaign=after-features-purchase-link)

= Related Posts for WordPress won't lag your server! =
We believe having related posts shouldn't slow down your website. That's why Related Posts for WordPress creates its own cache and does all the heavy lifting in the admin panel, offering you quality related posts while keeping your website fast!

= Automatically link posts to each other =
After installing the plugin you will be taking to a wizard that will analyze your posts and link them to each other based on what we think is related. This means you can install Related Posts for WordPress on your website that has thousands of posts and create related connections on the fly, without any manual work!

= Manually add, edit or remove =
Everyone makes mistakes, so do we. That's why you can easily modify all automatically created related posts. Simply navigate to the post that has incorrect related posts attached to it, select the correct related post and you're done.

= Shortcode =
Related Posts for WordPress has a related post shortcode allowing you to display related posts on any position within your content.

= Widget =
Related Posts for Wordpress has a related post widget allowing you to display related posts in any sidebar you'd like.

**More information**

- Visit the [Related Posts for WordPress website](http://www.relatedpostsforwp.com/?utm_source=wp-plugin-repo&utm_medium=link&utm_campaign=more-information)
- Other [WordPress plugins](http://profiles.wordpress.org/barrykooij/) by [Barry Kooij](http://www.barrykooij.com/)
- Contact Barry on Twitter: [@CageNL](http://twitter.com/CageNL)
- If you're a dev, follow or contribute to the [Related Posts for WordPress plugin on GitHub](https://github.com/barrykooij/related-posts-for-wp)


== Installation ==

= Installing the plugin =
1. In your WordPress admin panel, go to *Plugins > New Plugin*, search for *Related Posts for WordPress* and click "Install now"
1. Alternatively, download the plugin and upload the contents of `related-posts-for-wp.zip` to your plugins directory, which usually is `/wp-content/plugins/`.
1. Activate the plugin

== Frequently Asked Questions ==

> #### Related Posts for WordPress Premium
> There's an even better version of this plugin that comes with the following extra features:<br />
>
> - Cross Custom Post Type Support<br />
> - Multiple Related Post Styles<br />
> - Overwritable templates<br />
> - Custom Taxonomy Support<br />
> - Adjustable Weights<br />
> - WordPress Network / Multisite support<br />
> - Keep manually created links<br />
> - Priority Email Support<br />
>
> [More information](https://www.relatedpostsforwp.com/features/?utm_source=wp-plugin-repo&utm_medium=link&utm_campaign=faq-info-link) | [Upgrade >>](https://www.relatedpostsforwp.com/upgrade-premium/?utm_source=wp-plugin-repo&utm_medium=link&utm_campaign=faq-purchase-link)

= Where's the settings screen? =
Settings > Related Posts.

= Can the displaying of excerpt be disabled? =
Yes, set the excerpt length to 0 in the Related Posts for WordPress settings screen.

= Is there any way to custom CSS ? =
Yes, it's in the Related Posts for WordPress settings screen.

= Can the automatically outputted CSS be disabled? =
Yes, clear the CSS field in the Related Posts for WordPress settings screen.

= Is there a theme function so I can output this list anywhere in my theme I want? =
Yes, you can use `rp4wp_children();`.<br />
[More information on the theme function can be found here](https://www.relatedpostsforwp.com/documentation/theme-functions-to-display-related-posts/?utm_source=wp-plugin-repo&utm_medium=link&utm_campaign=faq-item).

= Is there a shortcode? =
Yes. To display your related posts via a shortcode use: `[rp4wp]`

= Can I limit the amount of related posts in the shortcode? =
Yes. Use the limit attribute, for example `[rp4wp limit=1]` for one related post.

= Is there a widget? =
Yes there is!

= Does Related Posts for WordPress supports WordPress Network / Multisite websites? =
No, the free version does not. [The premium version however does, get it here](https://www.relatedpostsforwp.com/checkout/?utm_source=wp-plugin-repo&utm_medium=link&utm_campaign=faq-item).

= Does Related Posts for WordPress uses it's own database table? =
There is one custom table created for the post cache, this table will however not be used at the frontend of your website. Related Posts are fetched with normal WP_Query objects.

== Screenshots ==
1. After activating Related Posts for WordPress, our wizard will automatically start. The first step is indexing and caching your posts.
2. The second step of the wizard is linking related posts for your existing content! You can of course also skip this step.
3. That's it! With one click you are good to go!
4. A new meta box is added to your post edit screens allowing you to link related posts, we will sort them on what we think is related (where top is most related).
5. Related posts are automatically added below your posts!

== Changelog ==

= 2.0.3: March 2, 2018 =
* Tweak: Removed hard removal of non a-z0-9 characters because this removes all non-latin chars causing issues for non-latin languages. Instead we're now using a specific blacklist of characters that needs to be removed. Also moved this to convert_characters so we apply this blacklist also to title,taxonomy,etc.
* Tweak: Made case lowering of words in cache UTF-8 compatible, solving an issue with non-latin characters.
* Tweak: We no longer cache all words but only the top 6. This greatly improves performance of the caching task.
* Tweak: Added filter 'rp4wp_cache_word_amount' to filter amount of words added in cache (default=6).

= 2.0.2: October 10, 2017 =
* Tweak: Fixed an issue where sticky posts were always included in related posts.
* Tweak: Removed a post type check since post type is always post.

= 2.0.1: September 7, 2017 =
* Tweak: Plugin is now checking if required mbstring PHP extension is installed.
* Tweak: Improved handling of plugin when premium version is also activated.
* Tweak: Updated various translations.

= 2.0.0: December 16, 2016 =
* Feature: New related post is found for parents of posts that are put back to draft or deleted.
* Feature: Related post are now removed when a post is put back to draft or deleted.
* Feature: Improved Content Matching Score algorithm. Better related content result. Rerun wizard for better results.
* Tweak: Fixed issue with search queries with multiple words in manual post linking.
* Tweak: Added post dates to manual linking screen.
* Tweak: Fixed jQuery lib include for HTTPS websites.
* Tweak: Added rp4wp_settings_sections filter to settings.
* Tweak: Renamed 'Restart wizard' to 'Rebuild Linkage'.
* Tweak: Added rp4wp_supported_post_types filter to various places.
* Tweak: Added keys to settings to allow for more detailed filtering.
* Tweak: Language updates for: NL, DE, FR, IT, BR, PT, RS, SE, UA.

= 1.9.3: May 2, 2016 =
* Tweak: Fixed a bug that caused the search query to reset when navigating through pages on the manual linking page.
* Tweak: Fixed unescaped page request variable on manual linking page.

= 1.9.2: April 18, 2016 =
* Tweak: We're now ignoring all characters that are not letters or numbers from word cache.
* Tweak: Various performance optimizations, props [Danny van Kooten](https://github.com/dannyvankooten).
* Tweak: Fixed a nonce error in installer when user clicked 'Skip linking'.
* Tweak: Various translations updates.

= 1.9.1: September 10, 2015 =
* Tweak: Fixed the `Woah! It looks like something else tried to run the Related Posts for WordPress` error message when resuming the wizard via the installing notice.

= 1.9.0: June 29, 2015 =
* Feature: Add pagination to manual post link table.
* Feature: Added id attribute to [rp4wp] shortcode.
* Feature: Added limit attribute to [rp4wp] shortcode.
* Feature: Added Brazilian Portuguese commonly used words.
* Feature: Added Czech commonly used words.
* Feature: Added Bulgarian commonly used words.
* Feature: Added Russian commonly used words.
* Feature: Added Swedish commonly used words.
* Feature: Added Spanish commonly used words.
* Feature: Added Norwegian Bokmål commonly used words.
* Tweak: Now displaying amount of posts left to link in installer.
* Tweak: Weight must be > 0 and words must be array in order to start adding words.
* Tweak: Add Composer autoloading (PHP 5.2 compatible) instead of custom autoloader, props [Danny van Kooten](https://github.com/dannyvankooten).
* Tweak: Static loading of hooks instead of directory scan, props [Danny van Kooten](https://github.com/dannyvankooten).
* Tweak: Added filter: rp4wp_get_children_link_args in RP4WP_Post_Link_Manager:get_children().
* Tweak: Added filter: rp4wp_get_children_child_args in RP4WP_Post_Link_Manager:get_children().
* Tweak: Fixed an issue with encoding non ASCII characters.

= 1.8.2: April 20, 2015 =
* Escaped view filter URL when manually linking posts to prevent possible XSS.

= 1.8.1: January 3, 2015 =
* Fixed a bug where UTF-8 encoded characters were not correctly parsed.
* Introduced icon alternative for when iconv isn't installed on server.

= 1.8.0: December 30, 2014 =
* Now preventing double form submitting in settings screen.
* Added plugin version to enqueued scripts.
* Added nonce checks to installer AJAX requests.
* Check installer nonce every step of installer.
* Added 'show love' option.
* Added dynamic option filter.
* Added warning on setting screen when an option is filtered.
* Added 'rp4wp_post_title' filter to filter related post titles.
* Added 'view post' link in manual link screen.
* Made related Posts block title WPML string translatable.
* Added translations: French, Italian, Portuguese, Portuguese (Brazil), Swedish.
* Updated translations: Dutch, German, Serbian.

= 1.7.6: December 2, 2014 =
* Added 'rp4wp_post_excerpt' filter.
* Added RTL support.
* Fixed a scheduled post bug.

= 1.7.5: November 15, 2014 =
* Fixed a hardcoded database table bug.
* Display notice and don't load plugin on multisite.

= 1.7.4: November 12, 2014 =
* Fixed an UTF-8 - iconv bug.
* Remove shortcodes from the related posts excerpt.

= 1.7.3: November 10, 2014 =
* Fixed multisite/network compatibility.

= 1.7.2: October 27, 2014 =
* Fixed a bug where permission were checked to soon.
* Removed an unused query var.
* Updated Dutch, German, Serbian, Swedish.

= 1.7.1: October 17, 2014 =
* Fixed a bug with manually creating links.

= 1.7.0: October 14, 2014 =
* Major performance improvements for post link creation.
* Fixed a shortcode bug.
* Changed NONCE_REINSTALL constant to NONCE_INSTALL.
* Added nonce checks.

= 1.6.3: September 18, 2014 =
* Fixed an install bug.

= 1.6.2: September 17, 2014 =
* Fixed a WSOD caused by wp_created_nonce being called before init hook.
* Changed the way the plugin is bootstrapped.

= 1.6.1: September 13, 2014 =
* Fixed a bug that caused the manual post link table to be empty.
* Added checks to only do certain checks in admin.

= 1.6.0: September 12, 2014 =
* We're now replacing 'special' characters with their 'normal' equivalent.
* Save the wizard settings to options to improve defaults.
* Added a percentage to the progressbar to improve progress clarity.
* The wizard progress is now updated when returned to wizard.
* Various caching tweaks.
* Added utf8 charset to cache database table in installer.

= 1.5.0: September 5, 2014 =
* Added Related Posts widget.
* Changed 'Delete Post' label to 'Unlink Related Post'.
* Lowered amount of posts cached per request to increase feedback in wizard.
* Various small wizard tweaks.
* Added nonce check to wizard restart procedure.
* Fixed a plugin activation redirect bug.

= 1.4.1: August 29, 2014 =
* Added filter 'rp4wp_append_content' to allow disabling of related post append to content.
* Fixed various hook and filter class bugs.

= 1.4.0: August 24, 2014 =
* Created the possibility to restart the installation wizard. See settings page for this option.
* Added notice that allows resuming installation wizard that will be displayed if the installation wizard crashed.
* Only load frontend CSS on singles.
* Fixed a default image size bug, props [Robert Neu](https://github.com/robneu).
* Small default CSS tweaks, props [Jackie D'Elia](https://github.com/savvyjackie).
* Complete plugin is now translatable.
* Updated Dutch translation.
* Default values are now in site language, if available.
* Uninstall script now also deletes the caching table.
* Added 'settings' link to plugins links.

= 1.3.2: August 22, 2014 =
* Fixed a bug where ignored words where not properly loaded.

= 1.3.1: August 21, 2014 =
* Added an uninstall procedure, see settings panel.
* Added plugin icons (yay).

= 1.3.0 : August 18, 2014 =
* Added 'rp4wp_children' template function.
* Added shortcode [rp4wp]
* Added pot translation template file.
* Added Dutch translation.
* Added review request admin notice.

= 1.2.2 : August 16, 2014 =
* Solved a conflict with the NextGen plugin (apply_filters recursion on same filter kills underlying call).
* Excerpt length is now set in words instead of characters.
* Small default CSS tweaks.

= 1.2.1 : August 14, 2014 =
* Fixed a small CSS default rule.

= 1.2.0 : August 14, 2014 =
* Related Posts heading text is now a setting.
* Amount of words in excerpt is now a setting.
* Displaying images of related posts is now a setting.
* Frontend CSS is now editable via settings.
* Added 'rp4wp_heading' filter, allows filtering of complete related posts block heading.

= 1.1.0 : August 11, 2014 =
* Added settings screen.
* Added to option to automatically link related posts on new posts, enabled by default.
* Added 'rp4wp_ignored_words' filter.
* Added 'rp4wp_thumbnail_size' filter.
* Added missing ABSPATH checks.

= 1.0.0 : August 7, 2014 =
* Initial version

== Upgrade Notice ==
This version is safe to upgrade. If all of your related posts are linked automatically, we do recommend rerunning the wizard for better results.
