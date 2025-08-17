# LLM Bot Tracker by Hueston

![WordPress Version](https://img.shields.io/badge/WordPress-6.5%2B-blue)
![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/license-GPL%20v2%2B-green)
![Version](https://img.shields.io/badge/version-1.3.1-orange)

Track and monitor LLM/AI bot visits to your WordPress site with detailed statistics and beautiful visualizations.

<div align="center">
  <img src="images/hueston-llmo-logo.png" alt="Hueston LLM Logo" width="200">
</div>

## 📊 Overview

LLM Bot Tracker is a comprehensive WordPress plugin that tracks and displays statistics for Large Language Model (LLM) and AI-related web crawlers visiting your site. Monitor visits from ChatGPT, Claude, Perplexity, and 25+ other AI bots to understand how your content is being used for AI training and search.

## ✨ Features

- **🤖 25+ Bot Detection**: Tracks major AI crawlers including:
  - OpenAI: GPTBot, ChatGPT-User, OAI-SearchBot
  - Anthropic: ClaudeBot, Claude-Web, Claude-SearchBot
  - Perplexity: PerplexityBot, Perplexity-User
  - Others: CCBot, Bytespider, Google-Extended, Meta bots, and more

- **📈 Multiple Display Options**: 5 specialized shortcodes for flexible presentation
- **🎨 Beautiful Visualizations**: Bar charts with customizable colors
- **⚡ Real-time Tracking**: See bot activity as it happens
- **🔍 Advanced Admin Dashboard**: Comprehensive logs with filtering at Tools > LLM Crawler Logs
- **🌍 IP Tracking**: Geographic information for bot requests
- **🚀 Performance Optimized**: Efficient database queries with optional caching

## 📦 Installation

1. Download the plugin from [WordPress.org](https://wordpress.org/plugins/llm-bot-tracker-by-hueston/) or this repository
2. Upload to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Start using the shortcodes to display bot statistics

## 🛠️ Usage

### Available Shortcodes

#### 1. Combined Stats Table
```
[wpcs_crawler_stats]
```
Shows both LLM bot statistics and last 100 hits (legacy compatibility).

#### 2. LLM Bot Statistics Only
```
[wpcs_llm_stats]
```
Clean table showing 24h/7d/30d hit counts for each LLM bot.

#### 3. Last 100 Hits
```
[wpcs_llm_last100]
```
Real-time view of the most recent crawler visits.

#### 4. Recent IPs List
```
[wpcs_llm_ip_list limit="20"]
```
Compact list of recent visitor IPs with bot favicons (perfect for sidebars).

#### 5. Visual Bar Chart
```
[wpcs_llm_bar window="7d" limit="10"]
```

**Parameters:**
- `window`: Time period - "24h", "7d" (default), or "30d"
- `limit`: Number of bots to display (1-20, default 10)
- `bar_start`: Start color for gradient (HEX)
- `bar_end`: End color for gradient (HEX)
- `track`: Background track color (HEX)
- `text`: Text color override (HEX)
- `ttl`: Cache time in seconds (5-3600, default 60)
- `nocache`: Set to "1" to bypass cache

### Example with Custom Colors
```
[wpcs_llm_bar bar_start="#0A1F44" bar_end="#D4AF37" track="rgba(255,255,255,0.15)" text="#FFFFFF"]
```

## 📸 Screenshots

1. **LLM Bot Statistics Table** - Shows 24h/7d/30d hit counts for each AI bot
2. **Admin Dashboard** - 30-day trend chart and top bots visualization
3. **Beautiful Bar Charts** - Visual representation of top AI bot activity
4. **Detailed Crawler Logs** - Advanced filtering and IP tracking
5. **Real-time Activity Feed** - Last 100 bot visits with full details

## 🔧 Admin Features

Access the admin dashboard at **Tools > LLM Crawler Logs** to:
- View comprehensive bot statistics with visual charts
- Filter logs by bot type, date range, path, or IP
- See 30-day trend analysis
- Export or delete log entries
- Track bot behavior patterns

## 📝 Changelog

### Version 1.3.1
- Fixed all WordPress.org plugin check errors and warnings for improved code quality and security

### Version 1.3.0
- Major update with focus on LLM/AI bot tracking
- Added 5 new specialized shortcodes for flexible display
- Implemented comprehensive admin dashboard
- Added visual analytics with trend charts and bar graphs
- Added IP tracking and geolocation features
- Performance optimizations with transient caching
- Added support for 15+ new AI-related bots

### Version 1.0.0
- Initial release with basic crawler tracking

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
```

## 🏢 About Hueston

Built with ❤️ by [Hueston](https://hueston.co)

## 🔗 Links

- [WordPress Plugin Page](https://wordpress.org/plugins/llm-bot-tracker-by-hueston/)
- [Support Forum](https://wordpress.org/support/plugin/llm-bot-tracker-by-hueston/)
- [Report Issues](https://github.com/HuestonCo/wordpress-llm-crawler-log/issues)

---

<div align="center">
  <strong>Track AI Bots • Understand Your Impact • Optimize Your Content</strong>
</div>
