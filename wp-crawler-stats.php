<?php
/**
 * Plugin Name: LLM Bot Tracker by Hueston
 * Plugin URI: https://github.com/HuestonCo/wordpress-llm-crawler-log
 * Description: Track and monitor LLM/AI bot visits to your WordPress site. Display statistics for GPTBot, ClaudeBot, PerplexityBot and 25+ other AI crawlers.
 * Version: 1.3.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Hueston
 * Author URI: https://hueston.co
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: llm-bot-tracker-by-hueston
 * Domain Path: /languages
 */

// Namespace and basic constants.
namespace WPCrawlerStats;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.3.0';
const DB_VERSION = '1.3.0';
const OPTION_DB_VERSION = 'wpcs_db_version';

/**
 * Convert HEX to rgba() string with alpha 0..1 for inline styles.
 *
 * @param string $hex
 * @param float  $alpha
 */
function hex_to_rgba( string $hex, float $alpha ): string {
    $hex = \sanitize_hex_color( $hex ) ?: '#000000';
    $alpha = max( 0.0, min( 1.0, $alpha ) );
    $hex = ltrim( $hex, '#' );
    if ( strlen( $hex ) === 3 ) {
        $r = hexdec( str_repeat( $hex[0], 2 ) );
        $g = hexdec( str_repeat( $hex[1], 2 ) );
        $b = hexdec( str_repeat( $hex[2], 2 ) );
    } else {
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
    }
    return sprintf( 'rgba(%d,%d,%d,%.3f)', $r, $g, $b, $alpha );
}

/**
 * Get the crawler hits table name.
 */
function get_table_name(): string {
    global $wpdb;
    return $wpdb->prefix . 'wpcs_hits';
}

/**
 * Create/upgrade database table.
 */
function install_or_upgrade(): void {
    global $wpdb;

    $table_name = get_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $schema = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        hit_date date NOT NULL,
        bot_name varchar(64) NOT NULL,
        url_path text NOT NULL,
        url_hash char(32) NOT NULL,
        hits bigint(20) unsigned NOT NULL DEFAULT 1,
        PRIMARY KEY  (id),
        UNIQUE KEY hit (hit_date, bot_name, url_hash),
        KEY bot_date (bot_name, hit_date),
        KEY url_hash (url_hash)
    ) {$charset_collate};";

    // Raw requests table for last-100 view.
    $requests_table = $wpdb->prefix . 'wpcs_requests';
    $schema_requests = "CREATE TABLE {$requests_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        hit_at datetime NOT NULL,
        bot_name varchar(64) NOT NULL,
        url_path text NOT NULL,
        url_hash char(32) NOT NULL,
        user_agent text NOT NULL,
        ip_address varchar(45) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        KEY bot_at (bot_name, hit_at),
        KEY url_hash (url_hash)
    ) {$charset_collate};";

    \dbDelta( $schema );
    \dbDelta( $schema_requests );
    \update_option( OPTION_DB_VERSION, DB_VERSION );
}
\register_activation_hook( __FILE__, __NAMESPACE__ . '\\install_or_upgrade' );

// Ensure DB upgrade runs when plugin is updated.
\add_action( 'admin_init', function () {
    $installed = (string) \get_option( OPTION_DB_VERSION, '' );
    if ( $installed !== DB_VERSION ) {
        install_or_upgrade();
    }
} );

/**
 * Utility: Determine bot name from user agent, or empty string if not a recognized bot.
 */
function detect_bot_name( string $user_agent ): string {
    $ua = \strtolower( $user_agent );
    // LLM-oriented and AI-related bots first.
    $bots = [
        'gptbot'           => 'GPTBot',
        'chatgpt-user'     => 'ChatGPT-User',
        'oai-searchbot'    => 'OAI-SearchBot',
        'claudebot'        => 'ClaudeBot',
        'claude-web'       => 'Claude-Web',
        'claude-searchbot' => 'Claude-SearchBot',
        'claude-user'      => 'Claude-User',
        'perplexitybot'    => 'PerplexityBot',
        'perplexity-user'  => 'Perplexity-User',
        'ccbot'            => 'CCBot',
        'omgilibot'        => 'Omgilibot',
        'omgili'           => 'Omgili',
        'mistralai-user'   => 'MistralAI-User',
        'google-extended'  => 'Google-Extended',
        'google-cloudvertexbot' => 'Google-CloudVertexBot',
        'applebot-extended'=> 'Applebot-Extended',
        'facebookbot'      => 'FacebookBot',
        'meta-externalagent' => 'Meta-ExternalAgent',
        'meta-externalfetcher' => 'Meta-ExternalFetcher',
        'bytespider'       => 'Bytespider',
        'amazonbot'        => 'Amazonbot',
        'archive.org_bot'  => 'archive.org_bot',
        'proratainc'       => 'ProRataInc',
        'timpibot'         => 'Timpibot',
        'petalbot'         => 'PetalBot',
        // Traditional crawlers and others.
        'googlebot'        => 'Googlebot',
        'bingbot'          => 'Bingbot',
        'duckduckbot'      => 'DuckDuckBot',
        'baiduspider'      => 'Baiduspider',
        'yandexbot'        => 'YandexBot',
        'applebot'         => 'Applebot',
        'yahoo! slurp'     => 'Yahoo! Slurp',
        'sogou'            => 'Sogou',
        'ahrefsbot'        => 'AhrefsBot',
        'semrushbot'       => 'SemrushBot',
        'mj12bot'          => 'MJ12bot',
        'dotbot'           => 'DotBot',
        'petalbot'         => 'PetalBot',
        'facebookexternalhit' => 'FacebookExternalHit',
        'twitterbot'       => 'Twitterbot',
        'linkedinbot'      => 'LinkedInBot',
        'redditbot'        => 'Redditbot',
        'uptimerobot'      => 'UptimeRobot',
        'curl'             => 'Curl',
        'wget'             => 'Wget',
    ];

    foreach ( $bots as $needle => $label ) {
        if ( \strpos( $ua, $needle ) !== false ) {
            return $label;
        }
    }

    // Not a known bot.
    return '';
}

/**
 * Labels for LLM-focused bots used for filtering and display.
 *
 * @return array<string>
 */
function get_llm_bot_labels(): array {
    return [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-Web',
        'Claude-SearchBot',
        'Claude-User',
        'PerplexityBot',
        'Perplexity-User',
        'CCBot',
        'Bytespider',
        'MistralAI-User',
        'Google-Extended',
        'Google-CloudVertexBot',
        'Applebot-Extended',
        'FacebookBot',
        'Meta-ExternalAgent',
        'Meta-ExternalFetcher',
        'PetalBot',
        'Amazonbot',
        'archive.org_bot',
        'ProRataInc',
        'Timpibot',
        'Omgilibot',
        'Omgili',
    ];
}

/**
 * Map bot label to a representative domain for favicon lookup.
 *
 * @param string $bot_name
 * @return string Domain name (without scheme)
 */
function get_bot_favicon_domain( string $bot_name ): string {
    $map = [
        'GPTBot'            => 'openai.com',
        'OAI-SearchBot'     => 'openai.com',
        'ChatGPT-User'      => 'openai.com',
        'ClaudeBot'         => 'anthropic.com',
        'Claude-Web'        => 'anthropic.com',
        'Claude-SearchBot'  => 'anthropic.com',
        'Claude-User'       => 'anthropic.com',
        'PerplexityBot'     => 'perplexity.ai',
        'Perplexity-User'   => 'perplexity.ai',
        'CCBot'             => 'commoncrawl.org',
        'Bytespider'        => 'bytedance.com',
        'Google-Extended'   => 'google.com',
        'Google-CloudVertexBot' => 'cloud.google.com',
        'Applebot-Extended' => 'apple.com',
        'Applebot'          => 'apple.com',
        'FacebookBot'       => 'facebook.com',
        'Meta-ExternalAgent'=> 'meta.com',
        'Meta-ExternalFetcher'=> 'meta.com',
        'MistralAI-User'    => 'mistral.ai',
        'DuckAssistBot'     => 'duckduckgo.com',
        'PetalBot'          => 'huawei.com',
        'Amazonbot'         => 'amazon.com',
        'archive.org_bot'   => 'archive.org',
        'ProRataInc'        => 'prorata.ai',
        'Timpibot'          => 'timpi.io',
        'Omgilibot'         => 'omgili.com',
        'Omgili'            => 'omgili.com',
    ];

    $domain = $map[ $bot_name ] ?? '';
    /**
     * Filter the favicon domain for a bot label.
     *
     * @param string $domain
     * @param string $bot_name
     */
    return (string) \apply_filters( 'wpcs_favicon_domain', $domain, $bot_name );
}

/**
 * Log a hit for recognized crawlers.
 */
function maybe_log_crawler_hit(): void {
    // Skip logging in admin, AJAX, cron, REST, feeds.
    if ( \is_admin() || \wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
        return;
    }
    if ( function_exists( '\\wp_is_json_request' ) && \wp_is_json_request() ) {
        return;
    }
    if ( function_exists( '\\is_feed' ) && \is_feed() ) {
        return;
    }

    $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    if ( $ua === '' ) {
        return;
    }

    $bot_name = detect_bot_name( $ua );
    if ( $bot_name === '' ) {
        return; // Not a recognized bot.
    }

    // Only log LLM-related bots; ignore traditional crawlers.
    if ( ! in_array( $bot_name, get_llm_bot_labels(), true ) ) {
        return;
    }

    // Build a normalized path for the current request (exclude query string).
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
    $absolute_url    = \home_url( $request_uri );
    $path            = (string) \wp_parse_url( $absolute_url, PHP_URL_PATH );
    if ( $path === '' ) {
        $path = '/';
    }

    // Skip 404s entirely.
    if ( function_exists( '\\is_404' ) && \is_404() ) {
        return;
    }

    $date = \current_time( 'Y-m-d' );
    $hash = \md5( $path );

    global $wpdb;
    $table = get_table_name();
    $requests_table = $wpdb->prefix . 'wpcs_requests';

    // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert.
    $sql = "INSERT INTO {$table} (hit_date, bot_name, url_path, url_hash, hits)
            VALUES (%s, %s, %s, %s, 1)
            ON DUPLICATE KEY UPDATE hits = hits + 1";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Logging hit data
    $wpdb->query( $wpdb->prepare( $sql, $date, $bot_name, $path, $hash ) );

    // Resolve IP address (best-effort; may pass proxies). Store first token.
    $ip = '';
    if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $xff   = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        $parts = array_map( 'trim', explode( ',', $xff ) );
        if ( ! empty( $parts ) ) {
            $ip = $parts[0];
        }
    }
    if ( $ip === '' && isset( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }

    // Also log raw row for last-100 view.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Logging request data
    $wpdb->insert(
        $requests_table,
        [
            'hit_at'   => \current_time( 'mysql' ),
            'bot_name' => $bot_name,
            'url_path' => $path,
            'url_hash' => $hash,
            'user_agent' => $ua,
            'ip_address' => $ip,
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s' ]
    );
}
\add_action( 'template_redirect', __NAMESPACE__ . '\\maybe_log_crawler_hit', 1 );

/**
 * Shortcode to render crawler stats.
 * Usage: [wpcs_crawler_stats period="7"] where period is 7, 30, 90, or all
 */
function shortcode_crawler_stats( $atts = [] ): string {
    // Accept but ignore previous 'period' attribute for backward compatibility.
    if ( is_array( $atts ) ) {
        $atts = array_map( 'sanitize_text_field', $atts );
    }

    global $wpdb;
    $hits_table     = get_table_name();
    $requests_table = $wpdb->prefix . 'wpcs_requests';

    // Define LLM bot names used in detect_bot_name.
    $llm_bots = get_llm_bot_labels();

    // Threshold datetimes (rolling windows) in site timezone.
    $now_ts   = \current_time( 'timestamp' );
    $t24_dt   = \wp_date( 'Y-m-d H:i:s', $now_ts - DAY_IN_SECONDS );
    $t7_dt    = \wp_date( 'Y-m-d H:i:s', $now_ts - 7 * DAY_IN_SECONDS );
    $t30_dt   = \wp_date( 'Y-m-d H:i:s', $now_ts - 30 * DAY_IN_SECONDS );

    // Build placeholders for IN clause.
    $placeholders = implode( ',', array_fill( 0, count( $llm_bots ), '%s' ) );

    // Combined query over raw requests to get rolling 24h, 7d, 30d counts per LLM bot.
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders for IN clause
    $sql_llm = $wpdb->prepare(
        "SELECT bot_name,
                SUM(CASE WHEN hit_at >= %s THEN 1 ELSE 0 END) AS d24,
                SUM(CASE WHEN hit_at >= %s THEN 1 ELSE 0 END) AS d7,
                SUM(CASE WHEN hit_at >= %s THEN 1 ELSE 0 END) AS d30
         FROM {$requests_table}
         WHERE bot_name IN ({$placeholders})
         GROUP BY bot_name
         ORDER BY bot_name ASC",
        array_merge( [ $t24_dt, $t7_dt, $t30_dt ], $llm_bots )
    );
    // phpcs:enable
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $llm_rows = $wpdb->get_results( $sql_llm );

    // Raw last 100 (404s are not logged).
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from prefix
    $sql_raw = "SELECT hit_at, bot_name, url_path, user_agent, ip_address FROM {$requests_table} ORDER BY id DESC LIMIT 100";
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $raw_rows = $wpdb->get_results( $sql_raw );

    // Compose both sections for backward compatibility.
    return render_wpcs_llm_stats_section( $llm_rows ) . render_wpcs_last100_section( $raw_rows );
}
\add_shortcode( 'wpcs_crawler_stats', __NAMESPACE__ . '\\shortcode_crawler_stats' );

/**
 * Helper: render LLM stats table section.
 *
 * @param array<int,object> $llm_rows
 */
function render_wpcs_llm_stats_section( array $llm_rows ): string {
    ob_start();
    ?>
    <div class="wpcs-llm" style="color: inherit;">
        <table class="wpcs-table" style="color:#fff;font-size:0.92em;">
            <thead>
                <tr>
                    <th><?php echo \esc_html__( 'LLM Bot', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th><?php echo \esc_html__( '24h', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th><?php echo \esc_html__( '7d', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th><?php echo \esc_html__( '30d', 'llm-bot-tracker-by-hueston' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $llm_rows ) ) : ?>
                <?php foreach ( $llm_rows as $row ) : ?>
                    <tr>
                        <td><?php echo \esc_html( (string) $row->bot_name ); ?></td>
                        <td><?php echo \esc_html( number_format_i18n( (int) $row->d24 ) ); ?></td>
                        <td><?php echo \esc_html( number_format_i18n( (int) $row->d7 ) ); ?></td>
                        <td><?php echo \esc_html( number_format_i18n( (int) $row->d30 ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="4"><?php echo \esc_html__( 'No data yet.', 'llm-bot-tracker-by-hueston' ); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Helper: render last-100 table section.
 *
 * @param array<int,object> $raw_rows
 */
function render_wpcs_last100_section( array $raw_rows ): string {
    ob_start();
    ?>
    <div class="wpcs-raw" style="color: inherit;">
        <table class="wpcs-table" style="color:#fff;font-size:0.92em;">
            <thead>
                <tr>
                    <th><?php echo \esc_html__( 'When', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th><?php echo \esc_html__( 'Bot', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th><?php echo \esc_html__( 'Page', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th><?php echo \esc_html__( 'IP', 'llm-bot-tracker-by-hueston' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $raw_rows ) ) : ?>
                <?php foreach ( $raw_rows as $row ) : ?>
                    <tr>
                        <td>
                            <?php
                            $dt_full = \mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row->hit_at, true );
                            $dt_us   = \mysql2date( 'm/d/y', (string) $row->hit_at, true );
                            ?>
                            <?php echo \esc_html( $dt_full ); ?> (<?php echo \esc_html( $dt_us ); ?>)
                        </td>
                        <td>
                            <?php
                            $bot_label   = (string) $row->bot_name;
                            $icon_domain = get_bot_favicon_domain( $bot_label );
                            $icon_url    = $icon_domain ? 'https://www.google.com/s2/favicons?sz=16&domain=' . rawurlencode( $icon_domain ) : '';
                            if ( $icon_url ) : ?>
                                <img src="<?php echo \esc_url( $icon_url ); ?>" alt="" width="16" height="16" class="wpcs-bot-icon" />
                            <?php endif; ?>
                            <?php echo \esc_html( $bot_label ); ?>
                        </td>
                        <td>
                            <?php $path = (string) $row->url_path; $url = \home_url( $path ); ?>
                            <a href="<?php echo \esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( $path ); ?></a>
                        </td>
                        <td><?php echo \esc_html( (string) $row->ip_address ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="4"><?php echo \esc_html__( 'No data yet.', 'llm-bot-tracker-by-hueston' ); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * [wpcs_llm_stats] — LLM bot totals (24h/7d/30d).
 */
function shortcode_wpcs_llm_stats( $atts = [] ): string {
    if ( is_array( $atts ) ) {
        $atts = array_map( 'sanitize_text_field', $atts );
    }

    global $wpdb;
    $requests_table = $wpdb->prefix . 'wpcs_requests';
    $llm_bots = get_llm_bot_labels();

    $now_ts = \current_time( 'timestamp' );
    $t24_dt = \wp_date( 'Y-m-d H:i:s', $now_ts - DAY_IN_SECONDS );
    $t7_dt  = \wp_date( 'Y-m-d H:i:s', $now_ts - 7 * DAY_IN_SECONDS );
    $t30_dt = \wp_date( 'Y-m-d H:i:s', $now_ts - 30 * DAY_IN_SECONDS );

    $placeholders = implode( ',', array_fill( 0, count( $llm_bots ), '%s' ) );
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders for IN clause
    $sql_llm = $wpdb->prepare(
        "SELECT bot_name,
                SUM(CASE WHEN hit_at >= %s THEN 1 ELSE 0 END) AS d24,
                SUM(CASE WHEN hit_at >= %s THEN 1 ELSE 0 END) AS d7,
                SUM(CASE WHEN hit_at >= %s THEN 1 ELSE 0 END) AS d30
         FROM {$requests_table}
         WHERE bot_name IN ({$placeholders})
         GROUP BY bot_name
         ORDER BY bot_name ASC",
        array_merge( [ $t24_dt, $t7_dt, $t30_dt ], $llm_bots )
    );
    // phpcs:enable
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $llm_rows = $wpdb->get_results( $sql_llm );

    return render_wpcs_llm_stats_section( $llm_rows );
}
\add_shortcode( 'wpcs_llm_stats', __NAMESPACE__ . '\\shortcode_wpcs_llm_stats' );

/**
 * [wpcs_llm_last100] — Last 100 crawler hits list.
 */
function shortcode_wpcs_llm_last100( $atts = [] ): string {
    if ( is_array( $atts ) ) {
        $atts = array_map( 'sanitize_text_field', $atts );
    }

    global $wpdb;
    $requests_table = $wpdb->prefix . 'wpcs_requests';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from prefix
    $sql_raw = "SELECT hit_at, bot_name, url_path, ip_address FROM {$requests_table} ORDER BY id DESC LIMIT 100";
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $raw_rows = $wpdb->get_results( $sql_raw );

    return render_wpcs_last100_section( $raw_rows );
}
\add_shortcode( 'wpcs_llm_last100', __NAMESPACE__ . '\\shortcode_wpcs_llm_last100' );

/**
 * [wpcs_llm_ip_list] — Compact list of recent IPs with bot favicon (good for sidebars).
 *
 * Attributes:
 * - limit: number of rows (default 20)
 */
function shortcode_wpcs_llm_ip_list( $atts = [] ): string {
    $limit = 20;
    if ( is_array( $atts ) ) {
        $atts = array_map( 'sanitize_text_field', $atts );
        if ( isset( $atts['limit'] ) ) {
            $limit = max( 1, min( 100, absint( $atts['limit'] ) ) );
        }
    }

    global $wpdb;
    $requests_table = $wpdb->prefix . 'wpcs_requests';
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from prefix, limit is cast to int
    $sql = "SELECT bot_name, ip_address FROM {$requests_table} WHERE ip_address <> '' ORDER BY id DESC LIMIT " . (int) $limit;
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results( $sql );

    ob_start();
    ?>
    <ul class="wpcs-iplist" style="color: inherit;">
        <?php if ( ! empty( $rows ) ) : ?>
            <?php foreach ( $rows as $row ) : ?>
                <?php
                $bot_label   = (string) $row->bot_name;
                $ip          = (string) $row->ip_address;
                $icon_domain = get_bot_favicon_domain( $bot_label );
                $icon_url    = $icon_domain ? 'https://www.google.com/s2/favicons?sz=16&domain=' . rawurlencode( $icon_domain ) : '';
                ?>
                <li class="wpcs-iplist-item">
                    <?php if ( $icon_url ) : ?>
                        <img src="<?php echo \esc_url( $icon_url ); ?>" alt="" width="16" height="16" class="wpcs-bot-icon" />
                    <?php endif; ?>
                    <span class="wpcs-ip"><?php echo \esc_html( $ip ); ?></span>
                </li>
            <?php endforeach; ?>
        <?php else : ?>
            <li class="wpcs-iplist-empty"><?php echo \esc_html__( 'No data yet.', 'llm-bot-tracker-by-hueston' ); ?></li>
        <?php endif; ?>
    </ul>
    <?php
    return (string) ob_get_clean();
}
\add_shortcode( 'wpcs_llm_ip_list', __NAMESPACE__ . '\\shortcode_wpcs_llm_ip_list' );


/**
 * [wpcs_llm_bar] — Horizontal bar chart of LLM bot hits for a time window.
 *
 * Attributes:
 * - window: '24h' | '7d' | '30d' (default '7d')
 * - limit: integer 1..20 (default 10)
 * - bar_start: HEX color for bar gradient start (optional; validated)
 * - bar_end: HEX color for bar gradient end (optional; validated)
 * - track: HEX color for background track (optional; validated)
 * - text: HEX color for text override (optional; validated)
 * - ttl: cache seconds (default 60, 5–3600)
 * - nocache: '1'|'true' to bypass cache
 */
function shortcode_wpcs_llm_bar( $atts = [] ): string {
    $window = '7d';
    $limit  = 10;

    // Hueston-like default palette (can be overridden via shortcode attrs).
    $default_bar_start = '#0A1F44'; // deep navy
    $default_bar_end   = '#D4AF37'; // gold
    $default_track     = 'rgba(255,255,255,0.15)';
    $default_text      = '#FFFFFF';

    $bar_start = $default_bar_start;
    $bar_end   = $default_bar_end;
    $track     = $default_track;
    $text_col  = $default_text;

    if ( is_array( $atts ) ) {
        $atts = array_map( 'sanitize_text_field', $atts );
        if ( isset( $atts['window'] ) ) {
            $win = strtolower( (string) $atts['window'] );
            if ( in_array( $win, [ '24h', '7d', '30d' ], true ) ) {
                $window = $win;
            }
        }
        if ( isset( $atts['limit'] ) ) {
            $limit = max( 1, min( 20, absint( $atts['limit'] ) ) );
        }
        // Optional caching controls.
        $ttl = 60;
        if ( isset( $atts['ttl'] ) ) {
            $ttl = max( 5, min( 3600, absint( $atts['ttl'] ) ) );
        }
        $nocache = false;
        if ( isset( $atts['nocache'] ) ) {
            $flag    = strtolower( (string) $atts['nocache'] );
            $nocache = in_array( $flag, [ '1', 'true', 'yes', 'y' ], true );
        }
        if ( isset( $atts['bar_start'] ) ) {
            $hex = (string) $atts['bar_start'];
            $bar_start = \sanitize_hex_color( $hex ) ?: $bar_start;
        }
        if ( isset( $atts['bar_end'] ) ) {
            $hex = (string) $atts['bar_end'];
            $bar_end = \sanitize_hex_color( $hex ) ?: $bar_end;
        }
        if ( isset( $atts['track'] ) ) {
            $hex = (string) $atts['track'];
            $valid = \sanitize_hex_color( $hex );
            if ( $valid ) {
                $track = $valid;
            }
        }
        if ( isset( $atts['text'] ) ) {
            $hex = (string) $atts['text'];
            $text = \sanitize_hex_color( $hex );
            if ( $text ) {
                $text_col = $text;
            }
        }
    }

    // Allow palette override via filter and build cache key before DB work.
    $palette = [
        'bar_start' => $bar_start,
        'bar_end'   => $bar_end,
        'track'     => $track,
        'text'      => $text_col,
    ];
    /**
     * Filter the color palette used by the LLM bar chart shortcode.
     *
     * @param array{bar_start:string,bar_end:string,track:string,text:string} $palette
     */
    $palette = (array) \apply_filters( 'wpcs_llm_bar_palette', $palette );
    $bar_start = isset( $palette['bar_start'] ) ? (string) $palette['bar_start'] : $bar_start;
    $bar_end   = isset( $palette['bar_end'] ) ? (string) $palette['bar_end'] : $bar_end;
    $track     = isset( $palette['track'] ) ? (string) $palette['track'] : $track;
    $text_col  = isset( $palette['text'] ) ? (string) $palette['text'] : $text_col;

    // Resolve cache TTL via filter and check cache.
    if ( ! isset( $ttl ) ) {
        $ttl = 60;
    }
    /**
     * Filter cache TTL in seconds for the bar shortcode.
     *
     * @param int    $ttl
     * @param string $window
     * @param int    $limit
     */
    $ttl = (int) \apply_filters( 'wpcs_llm_bar_cache_ttl', $ttl, $window, $limit );

    $cache_key_input = [ 'w' => $window, 'l' => $limit, 'p' => $palette, 'loc' => (string) get_locale() ];
    $cache_key       = 'wpcs_llm_bar_' . md5( wp_json_encode( $cache_key_input ) );
    if ( empty( $nocache ) ) {
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return (string) $cached;
        }
    }

    // Determine datetime threshold.
    $now_ts = \current_time( 'timestamp' );
    if ( $window === '24h' ) {
        $since_dt = \wp_date( 'Y-m-d H:i:s', $now_ts - DAY_IN_SECONDS );
        $label    = \__( 'Last 24h', 'llm-bot-tracker-by-hueston' );
    } elseif ( $window === '30d' ) {
        $since_dt = \wp_date( 'Y-m-d H:i:s', $now_ts - 30 * DAY_IN_SECONDS );
        $label    = \__( 'Last 30 days', 'llm-bot-tracker-by-hueston' );
    } else {
        $since_dt = \wp_date( 'Y-m-d H:i:s', $now_ts - 7 * DAY_IN_SECONDS );
        $label    = \__( 'Last 7 days', 'llm-bot-tracker-by-hueston' );
    }

    global $wpdb;
    $requests_table = $wpdb->prefix . 'wpcs_requests';
    $llm_bots       = get_llm_bot_labels();

    if ( empty( $llm_bots ) ) {
        return '<div class="wpcs-chart-empty">' . \esc_html__( 'No data yet.', 'llm-bot-tracker-by-hueston' ) . '</div>';
    }

    $placeholders = implode( ',', array_fill( 0, count( $llm_bots ), '%s' ) );
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders for IN clause
    $sql = $wpdb->prepare(
        "SELECT bot_name, COUNT(*) AS hits
         FROM {$requests_table}
         WHERE bot_name IN ({$placeholders}) AND hit_at >= %s
         GROUP BY bot_name
         ORDER BY hits DESC
         LIMIT %d",
        array_merge( $llm_bots, [ $since_dt, $limit ] )
    );
    // phpcs:enable

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results( $sql );
    if ( empty( $rows ) ) {
        return '<div class="wpcs-chart-empty">' . \esc_html__( 'No data yet.', 'llm-bot-tracker-by-hueston' ) . '</div>';
    }

    // Find max for scaling.
    $max = 0;
    foreach ( $rows as $r ) {
        $max = max( $max, (int) $r->hits );
    }
    if ( $max <= 0 ) {
        return '<div class="wpcs-chart-empty">' . \esc_html__( 'No data yet.', 'llm-bot-tracker-by-hueston' ) . '</div>';
    }

    $container_style = 'color:' . \esc_attr( $text_col ) . ';';

    // Rocket glow color derived from bar end.
    $glow = hex_to_rgba( $bar_end, 0.7 );

    ob_start();
    ?>
    <div class="wpcs-bar-chart" role="img" aria-label="<?php echo \esc_attr( sprintf( /* translators: %s = window label */ \__( 'LLM bot hits (%s)', 'llm-bot-tracker-by-hueston' ), $label ) ); ?>" style="<?php echo \esc_attr( $container_style ); ?>">
        <div class="wpcs-bar-chart-head" style="margin-bottom:8px;font-weight:600;">
            <?php echo \esc_html( sprintf( /* translators: %s = window label */ \__( 'LLM bot hits — %s', 'llm-bot-tracker-by-hueston' ), $label ) ); ?>
        </div>
        <div class="wpcs-bar-chart-body">
            <?php foreach ( $rows as $r ) :
                $name    = (string) $r->bot_name;
                $hits    = (int) $r->hits;
                $percent = max( 1, (int) floor( ( $hits / $max ) * 100 ) ); ?>
                <div class="wpcs-bar-row" style="display:flex;align-items:center;gap:8px;margin:6px 0;">
                    <div class="wpcs-bar-label" style="flex:0 0 140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?php echo \esc_html( $name ); ?>
                    </div>
                    <div class="wpcs-bar-track" style="flex:1 1 auto;height:12px;background:<?php echo \esc_attr( $track ); ?>;border-radius:6px;position:relative;overflow:hidden;">
                        <div class="wpcs-bar-contrail" style="position:absolute;left:0;top:50%;transform:translateY(-50%);height:6px;width:<?php echo (int) $percent; ?>%;background:linear-gradient(90deg, <?php echo \esc_attr( hex_to_rgba( $bar_start, 0.0 ) ); ?> 0%, <?php echo \esc_attr( hex_to_rgba( $bar_start, 0.35 ) ); ?> 55%, <?php echo \esc_attr( $bar_end ); ?> 100%);border-radius:6px;"></div>
                        <span class="wpcs-rocket" aria-hidden="true" style="position:absolute;left:<?php echo (int) $percent; ?>%;top:50%;transform:translate(-50%,-50%);width:12px;height:12px;pointer-events:none;display:block;">
                            <svg viewBox="0 0 24 24" width="12" height="12" role="img" aria-hidden="true" focusable="false" style="filter: drop-shadow(0 0 3px <?php echo esc_attr( $glow ); ?>);">
                                <path fill="<?php echo esc_attr( $bar_end ); ?>" d="M2 21l5-2 9-9-3-3-9 9-2 5zm13.6-14.6l2 2 3.1-3.1c.5-.5.5-1.3 0-1.8L19 1.8c-.5-.5-1.3-.5-1.8 0l-3.1 3.1 1.5 1.5z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="wpcs-bar-value" style="flex:0 0 auto;width:64px;text-align:right;">
                        <?php echo \esc_html( \number_format_i18n( $hits ) ); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    $html = (string) ob_get_clean();
    if ( empty( $nocache ) && $ttl > 0 ) {
        set_transient( $cache_key, $html, $ttl );
    }
    return $html;
}
\add_shortcode( 'wpcs_llm_bar', __NAMESPACE__ . '\\shortcode_wpcs_llm_bar' );


/**
 * Admin: Tools > Crawler Logs
 */
\add_action( 'admin_menu', function () {
    \add_management_page(
        \esc_html__( 'Crawler Logs', 'llm-bot-tracker-by-hueston' ),
        \esc_html__( 'LLM Crawler Logs', 'llm-bot-tracker-by-hueston' ),
        'manage_options',
        'wpcs-logs',
        __NAMESPACE__ . '\\render_wpcs_admin_logs_page'
    );
} );

// Hide unrelated admin notices on our Tools page to reduce noise.
\add_action( 'admin_head', function () {
    $screen = function_exists( '\\get_current_screen' ) ? \get_current_screen() : null;
    if ( $screen && isset( $screen->id ) && $screen->id === 'tools_page_wpcs-logs' ) {
        echo '<style>#wpbody-content > .notice, #wpbody-content > .updated, #wpbody-content > .error { display:none !important; }</style>';
    }
} );

/**
 * Render admin page for logs with filtering and deletion controls.
 */
function render_wpcs_admin_logs_page(): void {
    \current_user_can( 'manage_options' ) || \wp_die( \esc_html__( 'Unauthorized.', 'llm-bot-tracker-by-hueston' ) );

    global $wpdb;
    $requests_table = $wpdb->prefix . 'wpcs_requests';

    // Read filters (GET for view; also read from POST for delete actions).
    $get = function( string $key ): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters
        return isset( $_GET[ $key ] ) ? \sanitize_text_field( \wp_unslash( $_GET[ $key ] ) ) : '';
    };
    $post = function( string $key ): string {
        return isset( $_POST[ $key ] ) ? \sanitize_text_field( \wp_unslash( $_POST[ $key ] ) ) : '';
    };

    $filter_bot  = $get( 'bot' );
    $filter_path = $get( 'path' );
    $filter_ip   = $get( 'ip' );
    $filter_from = $get( 'from' ); // YYYY-MM-DD
    $filter_to   = $get( 'to' );   // YYYY-MM-DD

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameters
    $per_page = isset( $_GET['per_page'] ) ? max( 10, min( 200, absint( $_GET['per_page'] ) ) ) : 50;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameters
    $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

    // Handle deletions (POST).
    $notice = '';
    if ( isset( $_POST['wpcs_action'] ) ) {
        \check_admin_referer( 'wpcs_admin_logs_action', 'wpcs_admin_logs_nonce' );
        \current_user_can( 'manage_options' ) || \wp_die( \esc_html__( 'Unauthorized.', 'llm-bot-tracker-by-hueston' ) );

        $action = $post( 'wpcs_action' );

        if ( $action === 'delete_selected' ) {
            $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
            $ids = array_values( array_filter( $ids, function( $i ){ return $i > 0; } ) );
            if ( ! empty( $ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic placeholders and table name
                $deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$requests_table} WHERE id IN ($placeholders)", $ids ) );
                $notice  = sprintf( /* translators: %d = number deleted */ \esc_html__( 'Deleted %d entries.', 'llm-bot-tracker-by-hueston' ), $deleted );
            } else {
                $notice = \esc_html__( 'No entries selected.', 'llm-bot-tracker-by-hueston' );
            }
        } elseif ( $action === 'delete_filtered' ) {
            $cf_bot  = $post( 'bot' );
            $cf_path = $post( 'path' );
            $cf_ip   = $post( 'ip' );
            $cf_from = $post( 'from' );
            $cf_to   = $post( 'to' );

            $where = [ '1=1' ];
            $args  = [];
            if ( $cf_bot !== '' ) {
                $where[] = 'bot_name LIKE %s';
                $args[]  = '%' . $cf_bot . '%';
            }
            if ( $cf_path !== '' ) {
                $where[] = 'url_path LIKE %s';
                $args[]  = '%' . $cf_path . '%';
            }
            if ( $cf_ip !== '' ) {
                $where[] = 'ip_address LIKE %s';
                $args[]  = '%' . $cf_ip . '%';
            }
            if ( $cf_from !== '' ) {
                $where[] = 'hit_at >= %s';
                $args[]  = $cf_from . ' 00:00:00';
            }
            if ( $cf_to !== '' ) {
                $where[] = 'hit_at <= %s';
                $args[]  = $cf_to . ' 23:59:59';
            }

            $sql = 'DELETE FROM ' . $requests_table . ' WHERE ' . implode( ' AND ', $where );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic WHERE clause
            $deleted = (int) $wpdb->query( $wpdb->prepare( $sql, $args ) );
            $notice  = sprintf( /* translators: %d = number deleted */ \esc_html__( 'Deleted %d entries (filtered).', 'llm-bot-tracker-by-hueston' ), $deleted );
        }
        // After deletion, reset to page 1 to avoid empty page.
        $paged = 1;
    }

    // Build WHERE for viewing.
    $where = [ '1=1' ];
    $args  = [];
    if ( $filter_bot !== '' ) {
        $where[] = 'bot_name LIKE %s';
        $args[]  = '%' . $filter_bot . '%';
    }
    if ( $filter_path !== '' ) {
        $where[] = 'url_path LIKE %s';
        $args[]  = '%' . $filter_path . '%';
    }
    if ( $filter_ip !== '' ) {
        $where[] = 'ip_address LIKE %s';
        $args[]  = '%' . $filter_ip . '%';
    }
    if ( $filter_from !== '' ) {
        $where[] = 'hit_at >= %s';
        $args[]  = $filter_from . ' 00:00:00';
    }
    if ( $filter_to !== '' ) {
        $where[] = 'hit_at <= %s';
        $args[]  = $filter_to . ' 23:59:59';
    }

    $offset = ( $paged - 1 ) * $per_page;

    // Count total
    $sql_count = 'SELECT COUNT(*) FROM ' . $requests_table . ' WHERE ' . implode( ' AND ', $where );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin page query
    $total = (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $args ) );

    // Fetch page
    $sql_list = 'SELECT id, hit_at, bot_name, url_path, ip_address FROM ' . $requests_table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin page query
    $rows = (array) $wpdb->get_results( $wpdb->prepare( $sql_list, array_merge( $args, [ $per_page, $offset ] ) ) );

    $total_pages = max( 1, (int) ceil( $total / $per_page ) );

    // Render
    echo '<div class="wrap">';
    $logo_url = plugins_url( 'images/hueston-llmo-logo.png', __FILE__ );
    echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">';
    $site_url = 'https://hueston.co';
    echo '<a href="' . \esc_url( $site_url ) . '" target="_blank" rel="noopener noreferrer">';
    echo '<img src="' . \esc_url( $logo_url ) . '" alt="' . \esc_attr__( 'Hueston LLM', 'llm-bot-tracker-by-hueston' ) . '" style="height:40px;width:auto;" />';
    echo '</a>';
    echo '<h1 style="margin:0;">' . \esc_html__( 'Crawler Logs', 'llm-bot-tracker-by-hueston' ) . '</h1>';
    echo '</div>';
    if ( $notice !== '' ) {
        echo '<div class="notice notice-success"><p>' . \esc_html( $notice ) . '</p></div>';
    }

    // Summary charts (above table), minimal styling.
    $now_ts   = \current_time( 'timestamp' );
    $d7_dt    = \wp_date( 'Y-m-d H:i:s', $now_ts - 7 * DAY_IN_SECONDS );
    $d30_dt   = \wp_date( 'Y-m-d H:i:s', $now_ts - 30 * DAY_IN_SECONDS );
    $d90_dt   = \wp_date( 'Y-m-d H:i:s', $now_ts - 90 * DAY_IN_SECONDS );

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin chart data
    $daily_rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT DATE(hit_at) AS d, COUNT(*) AS c FROM ' . $requests_table . ' WHERE hit_at >= %s GROUP BY DATE(hit_at) ORDER BY d ASC', $d30_dt ) );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin chart data
    $topbot_rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT bot_name, COUNT(*) AS c FROM ' . $requests_table . ' WHERE hit_at >= %s GROUP BY bot_name ORDER BY c DESC LIMIT 8', $d7_dt ) );

    $daily_max = 0; $n = count( $daily_rows );
    $sum_x = 0.0; $sum_y = 0.0; $sum_xy = 0.0; $sum_x2 = 0.0; $i = 0;
    foreach ( $daily_rows as $r ) {
        $y = (int) $r->c; $daily_max = max( $daily_max, $y );
        $sum_x += $i; $sum_y += $y; $sum_xy += $i * $y; $sum_x2 += $i * $i; $i++;
    }
    $slope = 0.0; $intercept = 0.0;
    if ( $n > 1 ) {
        $den = ( $n * $sum_x2 - $sum_x * $sum_x );
        if ( $den != 0.0 ) {
            $slope = ( $n * $sum_xy - $sum_x * $sum_y ) / $den;
            $intercept = ( $sum_y - $slope * $sum_x ) / $n;
        }
    }

    // Summary header with totals and legend (7d/30d/90d)
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats
    $total_all = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $requests_table );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats
    $total_7   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $requests_table . ' WHERE hit_at >= %s', $d7_dt ) );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats
    $total_30  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $requests_table . ' WHERE hit_at >= %s', $d30_dt ) );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin stats
    $total_90  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $requests_table . ' WHERE hit_at >= %s', $d90_dt ) );

    echo '<div class="wpcs-admin-summary" style="display:flex;gap:16px;align-items:center;margin:8px 0 12px 0;flex-wrap:wrap;">';
    echo '<div class="wpcs-total" style="font-weight:700;font-size:18px;">' . \esc_html__( 'Total', 'llm-bot-tracker-by-hueston' ) . ': ' . \esc_html( number_format_i18n( $total_all ) ) . '</div>';
    echo '<div class="wpcs-legend" style="display:flex;gap:14px;align-items:center;">';
    echo '<span style="display:inline-flex;align-items:center;gap:6px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#D4AF37;"></span><span>7d ' . \esc_html( number_format_i18n( $total_7 ) ) . '</span></span>';
    echo '<span style="display:inline-flex;align-items:center;gap:6px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0A1F44;"></span><span>30d ' . \esc_html( number_format_i18n( $total_30 ) ) . '</span></span>';
    echo '<span style="display:inline-flex;align-items:center;gap:6px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#6b7280;"></span><span>90d ' . \esc_html( number_format_i18n( $total_90 ) ) . '</span></span>';
    echo '</div>';
    echo '</div>';

    // Minimal responsive layout + cards
    echo '<style>.wpcs-grid{display:grid;gap:16px;grid-template-columns:1fr}@media(min-width:900px){.wpcs-grid{grid-template-columns:2fr 1fr}}.wpcs-card{border:1px solid #e5e7eb;border-radius:8px;padding:12px}.wpcs-card h3{margin:0 0 8px 0;font-size:14px;font-weight:600}.wpcs-topbots-row{display:flex;align-items:center;gap:8px;margin:6px 0}.wpcs-topbots-label{flex:0 0 140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.wpcs-topbots-track{flex:1 1 auto;height:10px;background:#f3f4f6;border-radius:6px;position:relative}.wpcs-topbots-fill{height:100%;border-radius:6px;background:linear-gradient(90deg,#0A1F44,#D4AF37)}</style>';
    echo '<div class="wpcs-grid">';

    // Left: 30-day trend chart
    echo '<div class="wpcs-card">';
    echo '<h3>' . \esc_html__( 'Last 30 days (total hits per day)', 'llm-bot-tracker-by-hueston' ) . '</h3>';
    if ( ! empty( $daily_rows ) && $daily_max > 0 ) {
        $width  = 720; // viewBox width
        $height = 220; // viewBox height
        $mL = 42; $mR = 10; $mT = 18; $mB = 28;
        $plot_w = $width - $mL - $mR;
        $plot_h = $height - $mT - $mB;
        $n = count( $daily_rows );
        $step = ($n > 1) ? ( $plot_w / ($n - 1) ) : 0;

        $points = [];
        $i = 0;
        foreach ( $daily_rows as $r ) {
            $y_val = (int) $r->c;
            $cx = ($n > 1) ? ( $mL + $i * $step ) : ( $mL + $plot_w / 2 );
            $cy = $mT + $plot_h - ( $daily_max > 0 ? ($y_val / $daily_max) * $plot_h : 0 );
            $points[] = [ (int) round( $cx ), (int) round( $cy ), (string) $r->d, $y_val ];
            $i++;
        }
        $poly = '';
        foreach ( $points as $p ) { $poly .= $p[0] . ',' . $p[1] . ' '; }

        // Area path
        $area_d = 'M ' . $mL . ' ' . ( $mT + $plot_h ) . ' ';
        foreach ( $points as $p ) { $area_d .= 'L ' . $p[0] . ' ' . $p[1] . ' '; }
        $area_d .= 'L ' . ( $mL + $plot_w ) . ' ' . ( $mT + $plot_h ) . ' Z';

        $first_date = (string) $daily_rows[0]->d;
        $last_date  = (string) $daily_rows[ $n - 1 ]->d;

        echo '<svg role="img" aria-label="' . \esc_attr__( 'Daily hits (30 days)', 'llm-bot-tracker-by-hueston' ) . '" width="100%" viewBox="0 0 ' . (int) $width . ' ' . (int) $height . '">';
        // Gradient for area
        echo '<defs><linearGradient id="wpcsGrad" x1="0" y1="0" x2="0" y2="1">'
           . '<stop offset="0%" stop-color="#D4AF37" stop-opacity="0.35" />'
           . '<stop offset="100%" stop-color="#D4AF37" stop-opacity="0.05" />'
           . '</linearGradient></defs>';
        // Gridlines and Y ticks
        for ( $g = 0; $g <= 4; $g++ ) {
            $frac = $g / 4.0; $gy = $mT + $plot_h - ( $plot_h * $frac ); $val = (int) round( $daily_max * $frac );
            echo '<line x1="' . (int) $mL . '" y1="' . (int) $gy . '" x2="' . (int) ( $mL + $plot_w ) . '" y2="' . (int) $gy . '" stroke="#e5e7eb" stroke-width="1" />';
            echo '<text x="' . (int) ( $mL - 8 ) . '" y="' . (int) ( $gy + 4 ) . '" font-size="10" text-anchor="end" fill="#6b7280">' . \esc_html( number_format_i18n( $val ) ) . '</text>';
        }
        // X-axis labels (first, middle, last)
        $mid_idx = (int) floor( ( $n - 1 ) / 2 );
        $x_first = $mL; $x_last = $mL + $plot_w; $x_mid = ( $n > 1 ) ? ( $mL + $mid_idx * $step ) : ( $mL + $plot_w / 2 );
        $d_mid = (string) $daily_rows[ $mid_idx ]->d;
        echo '<text x="' . (int) $x_first . '" y="' . (int) ( $height - 6 ) . '" font-size="10" text-anchor="start" fill="#6b7280">' . \esc_html( $first_date ) . '</text>';
        echo '<text x="' . (int) $x_mid . '" y="' . (int) ( $height - 6 ) . '" font-size="10" text-anchor="middle" fill="#6b7280">' . \esc_html( $d_mid ) . '</text>';
        echo '<text x="' . (int) $x_last . '" y="' . (int) ( $height - 6 ) . '" font-size="10" text-anchor="end" fill="#6b7280">' . \esc_html( $last_date ) . '</text>';

        // Area + line
        echo '<path d="' . \esc_attr( $area_d ) . '" fill="url(#wpcsGrad)" />';
        echo '<polyline points="' . \esc_attr( trim( $poly ) ) . '" fill="none" stroke="#D4AF37" stroke-width="2" />';

        // Points (small)
        foreach ( $points as $p ) {
            echo '<circle cx="' . (int) $p[0] . '" cy="' . (int) $p[1] . '" r="2" fill="#0A1F44"><title>' . \esc_html( $p[2] . ': ' . $p[3] ) . '</title></circle>';
        }
        echo '</svg>';
    } else {
        echo '<div>' . \esc_html__( 'No data.', 'llm-bot-tracker-by-hueston' ) . '</div>';
    }
    echo '</div>';

    // Right: Top bots visual bars (7d)
    echo '<div class="wpcs-card">';
    echo '<h3>' . \esc_html__( 'Top bots — last 7 days', 'llm-bot-tracker-by-hueston' ) . '</h3>';
    $bot_max = 0; foreach ( $topbot_rows as $r ) { $bot_max = max( $bot_max, (int) $r->c ); }
    if ( ! empty( $topbot_rows ) && $bot_max > 0 ) {
        foreach ( $topbot_rows as $r ) {
            $name = (string) $r->bot_name; $c = (int) $r->c; $pct = max(1, (int) floor(($c / $bot_max) * 100));
            echo '<div class="wpcs-topbots-row">';
            echo '<div class="wpcs-topbots-label">' . \esc_html( $name ) . '</div>';
            echo '<div class="wpcs-topbots-track"><div class="wpcs-topbots-fill" style="width:' . (int) $pct . '%;"></div></div>';
            echo '<div style="flex:0 0 56px;text-align:right;">' . \esc_html( number_format_i18n( $c ) ) . '</div>';
            echo '</div>';
        }
    } else {
        echo '<div>' . \esc_html__( 'No data.', 'llm-bot-tracker-by-hueston' ) . '</div>';
    }
    echo '</div>';

    echo '</div>'; // .wpcs-grid

    // Top toolbar with compact filters and quick search.
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin filter options
    $bot_options = (array) $wpdb->get_col( 'SELECT DISTINCT bot_name FROM ' . $requests_table . ' ORDER BY bot_name ASC LIMIT 200' );

    echo '<div class="tablenav top">';
    echo '<div class="alignleft actions">';
    echo '<form method="get" action="" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
    echo '<input type="hidden" name="page" value="wpcs-logs" />';
    if ( ! empty( $bot_options ) ) {
        echo '<label>' . \esc_html__( 'Bot', 'llm-bot-tracker-by-hueston' ) . ' ';
        echo '<select name="bot">';
        echo '<option value="">' . \esc_html__( 'All', 'llm-bot-tracker-by-hueston' ) . '</option>';
        foreach ( $bot_options as $opt ) {
            $sel = ( $opt === $filter_bot ) ? ' selected' : '';
            echo '<option value="' . \esc_attr( (string) $opt ) . '"' . \esc_attr( $sel ) . '>' . \esc_html( (string) $opt ) . '</option>';
        }
        echo '</select></label>';
    } else {
        echo '<label>' . \esc_html__( 'Bot', 'llm-bot-tracker-by-hueston' ) . ' <input type="text" name="bot" value="' . \esc_attr( $filter_bot ) . '" class="regular-text" /></label>';
    }
    echo '<label>' . \esc_html__( 'Path contains', 'llm-bot-tracker-by-hueston' ) . ' <input type="text" name="path" value="' . \esc_attr( $filter_path ) . '" /></label>';
    echo '<label>' . \esc_html__( 'IP contains', 'llm-bot-tracker-by-hueston' ) . ' <input type="text" name="ip" value="' . \esc_attr( $filter_ip ) . '" /></label>';
    echo '<label>' . \esc_html__( 'From', 'llm-bot-tracker-by-hueston' ) . ' <input type="date" name="from" value="' . \esc_attr( $filter_from ) . '" /></label>';
    echo '<label>' . \esc_html__( 'To', 'llm-bot-tracker-by-hueston' ) . ' <input type="date" name="to" value="' . \esc_attr( $filter_to ) . '" /></label>';
    echo '<label>' . \esc_html__( 'Per page', 'llm-bot-tracker-by-hueston' ) . ' <input type="number" min="10" max="200" name="per_page" value="' . (int) $per_page . '" style="width:90px" /></label>';
    echo '<button class="button button-primary">' . \esc_html__( 'Filter', 'llm-bot-tracker-by-hueston' ) . '</button>';
    $reset_url = \admin_url( 'tools.php?page=wpcs-logs' );
    echo ' <a class="button" href="' . \esc_url( $reset_url ) . '">' . \esc_html__( 'Reset', 'llm-bot-tracker-by-hueston' ) . '</a>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Results + delete form (POST)
    echo '<form method="post" action="">';
    \wp_nonce_field( 'wpcs_admin_logs_action', 'wpcs_admin_logs_nonce' );
    echo '<input type="hidden" name="page" value="wpcs-logs" />';
    // Persist filters in POST for delete_filtered
    echo '<input type="hidden" name="bot" value="' . \esc_attr( $filter_bot ) . '" />';
    echo '<input type="hidden" name="path" value="' . \esc_attr( $filter_path ) . '" />';
    echo '<input type="hidden" name="ip" value="' . \esc_attr( $filter_ip ) . '" />';
    echo '<input type="hidden" name="from" value="' . \esc_attr( $filter_from ) . '" />';
    echo '<input type="hidden" name="to" value="' . \esc_attr( $filter_to ) . '" />';

    echo '<table class="widefat fixed striped" id="wpcs-logs-table">';
    echo '<thead><tr>';
    echo '<td class="manage-column column-cb check-column"><input type="checkbox" onclick="jQuery(\'.wpcs-cb\').prop(\'checked\', this.checked);" /></td>';
    echo '<th>' . \esc_html__( 'When', 'llm-bot-tracker-by-hueston' ) . '</th>';
    echo '<th>' . \esc_html__( 'Bot', 'llm-bot-tracker-by-hueston' ) . '</th>';
    echo '<th>' . \esc_html__( 'Page', 'llm-bot-tracker-by-hueston' ) . '</th>';
    echo '<th>' . \esc_html__( 'IP', 'llm-bot-tracker-by-hueston' ) . '</th>';
    echo '</tr></thead><tbody>';

    if ( ! empty( $rows ) ) {
        foreach ( $rows as $row ) {
            $dt_full = \mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row->hit_at, true );
            $path    = (string) $row->url_path;
            $url     = \home_url( $path );
            echo '<tr>';
            echo '<th scope="row" class="check-column"><input class="wpcs-cb" type="checkbox" name="ids[]" value="' . (int) $row->id . '" /></th>';
            echo '<td>' . \esc_html( $dt_full ) . '</td>';
            $bot_link = add_query_arg( [
                'page'     => 'wpcs-logs',
                'bot'      => (string) $row->bot_name,
                'path'     => $filter_path,
                'ip'       => $filter_ip,
                'from'     => $filter_from,
                'to'       => $filter_to,
                'per_page' => $per_page,
                'paged'    => 1,
            ], admin_url( 'tools.php' ) );
            echo '<td><a href="' . \esc_url( $bot_link ) . '">' . \esc_html( (string) $row->bot_name ) . '</a></td>';
            echo '<td><a href="' . \esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html( $path ) . '</a></td>';
            $ip_str  = (string) $row->ip_address;
            $ip_info = 'https://whatismyipaddress.com/ip/' . rawurlencode( $ip_str );
            echo '<td><a href="' . \esc_url( $ip_info ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html( $ip_str ) . '</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">' . \esc_html__( 'No results.', 'llm-bot-tracker-by-hueston' ) . '</td></tr>';
    }

    echo '</tbody></table>';

    echo '<p class="submit">';
    echo '<button type="submit" name="wpcs_action" value="delete_selected" class="button button-secondary" onclick="return confirm(\'' . \esc_js( __( 'Delete selected entries?', 'llm-bot-tracker-by-hueston' ) ) . '\');">' . \esc_html__( 'Delete selected', 'llm-bot-tracker-by-hueston' ) . '</button> ';
    echo '<button type="submit" name="wpcs_action" value="delete_filtered" class="button button-secondary" onclick="return confirm(\'' . \esc_js( __( 'Delete ALL entries matching current filters?', 'llm-bot-tracker-by-hueston' ) ) . '\');">' . \esc_html__( 'Delete filtered', 'llm-bot-tracker-by-hueston' ) . '</button>';
    echo '</p>';

    // Pagination controls.
    $base_url = add_query_arg( [
        'page'     => 'wpcs-logs',
        'bot'      => rawurlencode( $filter_bot ),
        'path'     => rawurlencode( $filter_path ),
        'ip'       => rawurlencode( $filter_ip ),
        'from'     => rawurlencode( $filter_from ),
        'to'       => rawurlencode( $filter_to ),
        'per_page' => $per_page,
    ], admin_url( 'tools.php' ) );

    echo '<div class="tablenav"><div class="tablenav-pages">';
    echo '<span class="displaying-num">' . (int) $total . ' ' . \esc_html__( 'items', 'llm-bot-tracker-by-hueston' ) . '</span> ';
    if ( $total_pages > 1 ) {
        $prev = max( 1, $paged - 1 );
        $next = min( $total_pages, $paged + 1 );
        echo '<a class="button" href="' . \esc_url( add_query_arg( 'paged', $prev, $base_url ) ) . '">« ' . \esc_html__( 'Prev', 'llm-bot-tracker-by-hueston' ) . '</a> ';
        echo '<span style="margin:0 8px;">' . (int) $paged . ' / ' . (int) $total_pages . '</span> ';
        echo '<a class="button" href="' . \esc_url( add_query_arg( 'paged', $next, $base_url ) ) . '">' . \esc_html__( 'Next', 'llm-bot-tracker-by-hueston' ) . ' »</a>';
    }
    echo '</div></div>';

    echo '</form>';
    echo '</div>';
}

