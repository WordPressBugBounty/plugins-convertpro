=== EasyTest - Simplify A/B Testing ===
Contributors: wpgrids, ashrafuddin765
Tags: ab testing, split testing, ab test, conversion rate, landing page
Requires at least: 5.0
Requires PHP: 7.0
Tested up to: 7.0
Stable tag: 1.0.3
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

A/B test your WordPress pages, headlines and buttons. Split your traffic, track conversions, and see which version actually wins.

== Description ==

Not sure whether the new landing page beats the old one? Run an A/B test and find out instead of guessing.

EasyTest is a split testing plugin for WordPress. You make two versions of a page, or two versions of one thing on a page such as a headline or a button. EasyTest sends half your visitors to each, counts how many convert, and shows you both conversion rates side by side.

It runs on your own site. No account to create, no monthly fee, and no limit on how many visitors you send through a test.

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
Only if you switch on the optional usage report, and it is off until you do. If you turn it on, once a week EasyTest sends wpgrids.com your site address and name, the administrator email and name, your server IP, your WordPress, PHP, MySQL, server and theme versions, how many plugins and users you have, when you installed EasyTest, and how many tests you are running. We use it to decide what to build next, and you can switch it off again from the Plugins screen.

= Do I have to fill in the form when I deactivate? =
No. Skipping it deactivates the plugin just the same.

== Screenshots ==

1. Every test you have running, in one list.
2. Setting up a test: name it, pick your two versions, say what counts as a conversion.
3. The report: views, conversions and conversion rate for each version, day by day.

== Changelog ==

= 1.0.3 =

If you ran a test on an earlier version, the numbers it gave you were probably wrong. This release fixes that.

* Traffic is split at random in the ratio you set. Before, early visitors all got the same version, and splits like 25/75 came out wrong.
* Conversions are recorded properly. They used to stop counting an hour after a visitor entered a test, element tests often recorded nothing, and a 404 could count as a conversion.
* A test saved with no URL was taking over the home page. A test URL that matched a page you already had could send visitors round in a circle until the browser gave up. Both are refused now, and the test list names any old ones that need fixing.
* utm tags, gclid and your own parameters carry over to the version the visitor lands on. They used to be dropped at the redirect.
* Returning visitors go to the right page. They were sent to a guessed address that could 404.
* Works with page caches. Only the URL that decides the split is kept out of the cache, so the pages you are testing stay cached.
* New: click goals. Count a click on a link or button instead of a page visit, even when it goes to another site like an external checkout.
* New: reset a test to start a fresh run. Earlier runs are kept.
* Reports show views and conversions separately for each version, with the conversion rate against your control.
* Adding or removing a version on a saved test now saves. Adding used to do nothing, and removing only cleared it from the screen.
* Faster on sites running several tests, and the plugin loads on PHP 7.0 again.
* Security: removed an unused route that could record a conversion for a logged-out visitor.

= 1.0.2 =

* Fixed a security issue reported by Patchstack (CVE-2025-63031): the reporting endpoints could be read without being logged in.
* Tightened several other checks found while looking at it: a cross-site scripting hole on the report screen, the usage-tracking opt-out that turned tracking on instead of off, a missing check on the deactivation form, and an unprepared database query.

= 1.0.1 =

* Renamed from Convert Pro to EasyTest - Simplify A/B Testing.

= 1.0.0 =

* First release: A/B testing for pages and for elements within a page.
