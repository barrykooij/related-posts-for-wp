=== Related Posts for WordPress ===
Contributors: barrykooij
Donate link: http://www.barrykooij.com/
Tags: related posts for wordpress, related posts for wp, simple related posts, easy related posts, related posts, related, relations, internal links, seo
Requires at least: 3.6
Tested up to: 4.0
Stable tag: 1.4.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display related posts without slowing down your website! Link all your existing content with only 1 click, get related posts for all your posts today!

== Description ==

= Related Posts for WordPress =

Related Posts for WordPress offers you the ability to link related posts to each other with just 1 click!

Our installation wizard will do all the hard work for you, simply activate the plugin, set the amount of posts that should relate and press the button. Related Posts for WordPress will do the rest. Relating posts in WordPress has never been this easy!

= Related Posts for WordPress won't lag your server! =
We don't think having related posts should slow down your website. That's why Related Posts for WordPress creates its own cache and does all the heavy lifting in the admin panel, keeping your website fast as it should be!

= Automatically link posts to each other =
After installing the plugin you will be taking to a wizard that will analyze your posts and link them to each other based on what we think is related. This means you can install Related Posts for WordPress on your website that has thousands of posts and create related connections on the fly, without any manual work!

= Manually add, edit or remove =
Everyone makes mistakes, so do we. That's why you can easily modify all automatically created related posts. Simply navigate to the post that has incorrect related posts attached to it, edit it and you're done.

= WPML compatible =
Related Posts for WordPress is fully compatible with WPML. You can automatically and manually link related posts in their own language.

**More information**

- Other [WordPress plugins](http://profiles.wordpress.org/barrykooij/) by [Barry Kooij](http://www.barrykooij.com/)
- Contact Barry on Twitter: [@CageNL](http://twitter.com/CageNL)
- If you're a dev, follow or contribute to the [Related Posts for WordPress plugin on GitHub](https://github.com/barrykooij/related-posts-for-wp)


== Installation ==

= Installing the plugin =
1. In your WordPress admin panel, go to *Plugins > New Plugin*, search for *Related Posts for WordPress* and click "Install now"
1. Alternatively, download the plugin and upload the contents of `related-posts-for-wp.zip` to your plugins directory, which usually is `/wp-content/plugins/`.
1. Activate the plugin

== Frequently Asked Questions ==

= Where's the settings screen? =
Settings > Related Posts.

= Can the displaying of excerpt be disabled? =
Yes, set the excerpt length to 0 in the Related Posts for WordPress settings screen.

= Is there any way to custom CSS ? =
Yes, it's in the Related Posts for WordPress settings screen.

= Can the automatically outputted CSS be disabled? =
Yes, clear the CSS field in the Related Posts for WordPress settings screen.

= Is there a theme function so I can output this list anywhere in my theme I want? =
Not yet, we're working on this and this will be added soon!

= Is there a shortcode? =
Yes, use [rp4wp]

= Does the plugin uses it's own database table ? =
There is one custom table created for the post cache, this table will however not be used at the frontend of your website. Related Posts are fetched with normal WP_Query objects.

== Screenshots ==
1. After activating Related Posts for WordPress, our wizard will automatically start. The first step is indexing and caching your posts.
2. The second step of the wizard is linking related posts for your existing content! You can of course also skip this step.
3. That's it! With one click you are good to go!
4. A new meta box is added to your post edit screens allowing you to link related posts, we will sort them on what we think is related (where top is most related).
5. Related posts are automatically added below your posts!

== Changelog ==

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
This version is safe to upgrade.