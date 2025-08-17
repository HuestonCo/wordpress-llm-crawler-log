=== LLM Bot Tracker by Hueston ===
Contributors: huestonwins
Tags: llm, ai, crawler, stats, analytics
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track and monitor LLM/AI bot visits to your WordPress site with detailed statistics and visualizations.

== Description ==
LLM Bot Tracker tracks and displays statistics for LLM (Large Language Model) and AI-related web crawlers visiting your WordPress site. Monitor visits from ChatGPT, Claude, Perplexity, and other AI bots to understand how your content is being used for AI training and search.

= Key Features =
* Tracks 25+ LLM/AI bots including GPTBot, ClaudeBot, PerplexityBot, CCBot, Bytespider, and more
* Multiple display shortcodes for flexible presentation
* Real-time statistics with customizable time windows (24h, 7d, 30d)
* Comprehensive admin dashboard with visual analytics and filtering
* Visual bar charts with customizable colors
* IP tracking and geolocation lookup
* Lightweight and performance-optimized with caching
* Privacy-focused - only tracks AI bots, not human visitors

= Available Shortcodes =
* `[wpcs_crawler_stats]` - Combined stats table and last 100 hits
* `[wpcs_llm_stats]` - LLM bot statistics table only
* `[wpcs_llm_bar window="7d" limit="10"]` - Beautiful horizontal bar chart
* `[wpcs_llm_last100]` - Last 100 crawler hits table
* `[wpcs_llm_ip_list limit="20"]` - Compact IP list for sidebars

= Perfect For =
* Content creators wanting to track AI bot activity
* SEO professionals monitoring LLM crawler behavior
* Publishers understanding their AI search visibility
* Anyone curious about how AI systems interact with their content

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add any of the shortcodes to pages, posts, or widgets
4. View detailed logs at Tools > LLM Crawler Logs

== Frequently Asked Questions ==

= Does this track human visitors? =
No. It exclusively tracks identified AI/LLM crawlers and bots, not human visitors.

= Which AI bots are tracked? =
The plugin tracks over 25 AI-related bots including GPTBot (OpenAI), ClaudeBot (Anthropic), PerplexityBot, CCBot (Common Crawl), Bytespider (ByteDance), Google-Extended, Meta-ExternalAgent, and many more.

= Can I customize the appearance? =
Yes! The bar chart shortcode supports custom colors via attributes:
`[wpcs_llm_bar bar_start="#0A1F44" bar_end="#D4AF37" track="#rgba(255,255,255,0.15)" text="#FFFFFF"]`

= How are hits counted? =
Each unique bot visit to a URL is counted. Multiple visits from the same bot to the same URL on the same day are aggregated.

= Will this slow down my site? =
No. The plugin is highly optimized with efficient database queries and optional caching for display shortcodes.

= Can I export the data? =
Currently, data can be viewed in the admin interface. Export functionality may be added in future versions.

== Screenshots ==
1. LLM bot statistics table showing 24h/7d/30d hit counts
2. Admin dashboard with 30-day trend chart and top bots visualization
3. Beautiful bar chart visualization of top AI bots
4. Detailed crawler logs with advanced filtering options
5. Last 100 hits table showing real-time bot activity

== Changelog ==
= 1.3.1 =
* Fixed all WordPress.org plugin check errors and warnings for improved code quality and security

= 1.3.0 =
* Major update with focus on LLM/AI bot tracking
* Added 5 new specialized shortcodes for flexible display
* Implemented comprehensive admin dashboard at Tools > LLM Crawler Logs
* Added visual analytics with trend charts and bar graphs
* Added IP tracking and geolocation features
* Performance optimizations with transient caching
* Added support for 15+ new AI-related bots
* Improved database schema for better performance
* Added real-time hit tracking alongside aggregated stats

= 1.0.0 =
* Initial release with basic crawler tracking

== Upgrade Notice ==
= 1.3.1 =
Minor update to fix WordPress.org plugin check errors and warnings. Improves code quality and security standards compliance.

= 1.3.0 =
Major update focusing on LLM/AI bot tracking with new admin interface, multiple display options, and visual analytics. Recommended for all users.