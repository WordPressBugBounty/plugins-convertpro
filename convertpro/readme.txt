=== EasyTest - Simplify A/B Testing ===
Contributors: wpgrids, ashrafuddin765
Tags: ab testing, split testing, ab test, conversion rate, landing page
Requires at least: 5.0
Requires PHP: 7.0
Tested up to: 7.1
Stable tag: 1.0.4
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

A/B test your pages, headlines and buttons. Runs on your own site: no account, no visitor limit, and your data never leaves.

== Description ==

Not sure whether the new landing page beats the old one? Run an A/B test and find out instead of guessing.

EasyTest is a split testing plugin for WordPress. You make two versions of a page, or two versions of one thing on a page such as a headline or a button. EasyTest sends half your visitors to each, counts how many convert, and shows you both conversion rates side by side.

It runs entirely on your own site:

* **No account.** Install it and start. There is nothing to sign up for and nothing to connect.
* **No visitor limit.** A hundred visitors or a hundred thousand, it is the same. No monthly cap, no meter running out mid-test.
* **Your data stays put.** Every view and every conversion is a row in your own database. None of it leaves your site.

= Features =

* **Page A/B testing.** Compare two landing pages, sales pages, or any two pages on your site.
* **Element A/B testing.** Test one part of a page instead of the whole thing. Swap a headline, a button, a price or a whole section, and leave the rest alone.
* **Set your own traffic split.** 50/50, 70/30, or whatever you want. Visitors are picked at random and keep seeing the same version if they come back.
* **Page goals and click goals.** Count a visit to a thank you page, or a click on a link or button. Click goals still work when the button goes to another site, like an external checkout.
* **Conversion rate reporting.** Views, conversions and the conversion rate for each version, and how every version compares with your control.
* **Works with caching plugins.** Only the URL that decides the split is kept out of the cache. The pages you are testing stay cached, so your site stays fast.
* **Works with Elementor and Gutenberg.** Element tests use a CSS class, so any builder or theme that lets you add one will work.

= What it cannot do =

A cache that sits in front of WordPress, like Cloudflare or a host level cache, never runs PHP, so the plugin cannot reach it. EasyTest spots these and tells you the one rule to add. Everything inside WordPress is handled for you.

== Installation ==

1. Install and activate EasyTest from Plugins, Add New. Or upload the folder to `/wp-content/plugins/`.
2. Open **EasyTest** in the admin menu and click **Add Test**.
3. Name the test, pick whether you are comparing pages or one element, and give it a test URL. Make one up. It cannot be a page you already have.
4. Pick your two versions and say what counts as a conversion, then save.
5. Send your traffic to the test URL. EasyTest handles the rest.

== Frequently Asked Questions ==

= Is there a limit on how much traffic I can test? =
No. A hundred visitors or a hundred thousand, it costs the same, because it all runs on your own site. There is no account and no monthly quota to run out of.

= Do I need to know how to code? =
No. Page tests need no code at all. For element tests you copy a CSS class and paste it into your page builder. That is the whole job.

= Can I A/B test a whole page, or just part of one? =
Both. A page test compares two separate pages. An element test changes one part of a single page and leaves the rest as it is.

= Does EasyTest work with Elementor? =
Yes. Element tests use a CSS class, and Elementor lets you add one to any widget or section. Elementor Pro popups and saved templates are not supported yet.

= Does it work with Gutenberg blocks? =
Yes. Put your class in the block's Advanced, Additional CSS class(es) box.

= Does it work with my theme? =
Page tests work with any theme. Element tests work with any theme that lets you add a CSS class, which is most of them.

= Will a caching plugin break my A/B test? =
No. EasyTest keeps the URL that decides the split out of the cache and leaves everything else cached, so your site stays fast. If you also run a cache in front of WordPress like Cloudflare, EasyTest spots it and tells you the one rule to add there.

= How long should I run a test? =
Until the numbers stop jumping around. With only a few visitors the gap between two versions swings a lot, so give it real traffic before you call a winner.

= What if the same person comes back later? =
They see the same version they saw the first time. EasyTest remembers for 30 days, so one person cannot count twice and skew your conversion rate.

= What does EasyTest store about my visitors? =
A cookie so they keep seeing the same version, and one row in your own database recording that visit and whether it converted. The id in that row is a random string, not a name, an email address or an IP address. None of it ever leaves your site.

= Does EasyTest send my data anywhere? =
Only if you switch on the optional usage report, and it is off until you do. If you turn it on, once a week EasyTest sends wpgrids.com your site address and name, the administrator email and name, your server IP, your WordPress, PHP, MySQL, server and theme versions, how many plugins and users you have, when you installed EasyTest, how many tests you are running, and whether you answered the review question and clicked through to WordPress.org. We use it to decide what to build next, and you can switch it off again from the Plugins screen.

= Do I have to fill in the form when I deactivate? =
No. Skipping it deactivates the plugin just the same.

== Screenshots ==

1. Every test you have running, in one list.
2. Setting up a test: name it, pick your two versions, say what counts as a conversion.
3. The report: views, conversions and conversion rate for each version, day by day.

== Changelog ==

= 1.0.4 =

* Fixed: Blank page on bad class
* Fixed: Wrong dates in the report
* Fixed: Traffic split ignored your setting
* Improved: Faster admin and front end
* Security: Removed an unused handler
* New: Asks once if EasyTest helped

= 1.0.3 =

* Fixed: Traffic split was uneven
* Fixed: Conversions stopped counting early
* Fixed: Broken and looping test URLs
* Improved: Report shows every version
* New: Click goals and test reset
* Security: Removed an unused route

= 1.0.2 =

* Security: Fixed CVE-2025-63031 (Patchstack)
* Security: Report data readable without login
* Security: Closed a cross-site scripting hole
* Fixed: Tracking opt-out worked backwards

= 1.0.1 =

* Renamed from Convert Pro to EasyTest - Simplify A/B Testing.

= 1.0.0 =

* First release: A/B testing for pages and for elements within a page.
