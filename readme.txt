=== JournalPress ===
Contributors: alisdee
Tags: dreamwidth, crossposting, community, post, posts, social, update
Requires at least: 4.9.1
Tested up to: 6.5.5
Stable tag: 1.2

A cross-poster supporting Dreamwidth and similar (i.e., LiveJournal-based) sites.

== Description ==

**JournalPress** is a WordPress plugin that enabled cross-posting to sites running LiveJournal Server, which in 2024 is basically [Dreamwidth](https://www.dreamwidth.org/) (and, ironically, not LiveJournal itself). It is based on the LJXP plugin, however it has a raft of new features including:

* support for multiple different mirror journals
* support for scheduled posts
* support for posts created from interfaces (i.e. XML-RPC, Atom)
* mood, music and location support
* per-post-per-journal userpic selection.

= Version 1.2 =
* Minor bugfixes.

= Version 1.1 =
* Updated instructions for using API keys.

= Version 1.0 =
* Significant code rewrite, so make sure to check your **settings** and **journals** as some config items may not have migrated exactly as expected!
* Support for LJ-style cut plugins on the WordPress end removed. WordPress-native `<!--more-->` still supported.
* Support for customer user groups for posting locking removed, since the plugin it relied on is super broken.
* Bulk crossposting options removed.

== Installation ==

1. Upload the `journalpress` folder to the `/wp-content/plugins/` directory;
1. Activate the plugin through the 'Plugins' menu in WordPress;
1. ...
1. Profit!

== Frequently Asked Questions ==

= Where are my userpics? =

Userpics should get automatically added when you add a new mirror journal or community.

If they don't -- or if you've upgraded from a pre-0.3 version of JournalPress -- you may fix the list manually on the edit journal page. If you add or delete any pics in the future, you will also need to come back to this page in order to update your list.

= What about Currents? =

There's current (har har) basic Currents support using the Custom Fields `mood`, `music` and `location` on a post. Note these are case-sensitive, and all lowercase.

= My community posts don't post! =

Communities in LJ-land are a bit finicky. Specifically they won't post if your security is private, and they won't post under certain backdated conditions.

== Credits ==

**JournalPress** is based off the original [LJXP](http://ebroder.net/livejournal-crossposter/) client by Evan Broder, with the [LJ Crossposter Plus](http://www.alltrees.org/Wordpress/#LCP) modifications made by Ravenwood and Irwin. No disrespect is intended towards any of these authors; without their great work, this plugin wouldn't have been possible (or at least would've taken a hell of a lot longer to write).

Big shout-out to everyone who's kept using this terrible old code all this time. You guys rock!