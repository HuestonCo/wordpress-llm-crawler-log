<?php
/**
 * Plugin Name: LLM Bot Tracker by Hueston
 * Plugin URI: https://github.com/HuestonCo/wordpress-llm-crawler-log
 * Description: Track and monitor LLM/AI bot visits to your WordPress site. Display statistics for GPTBot, ClaudeBot, PerplexityBot and 27 other AI crawlers.
 * Version: 1.4.4
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
namespace LLMBotTrackerByHueston;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.4.4';
const DB_VERSION = '1.4.1';
const OPTION_DB_VERSION = 'wpcs_db_version';

/**
 * Convert HEX to rgba() string with alpha 0..1 for inline styles.
 *
 * @param string $hex
 * @param float  $alpha
 */
if ( ! function_exists( __NAMESPACE__ . '\\hex_to_rgba' ) ) {
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
    
    // AI Blind Spots analysis table (v1.4.0)
    $blindspots_table = $wpdb->prefix . 'wpcs_page_analysis';
    $schema_blindspots = "CREATE TABLE {$blindspots_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        last_ai_visit datetime DEFAULT NULL,
        ai_score tinyint unsigned DEFAULT 0,
        issues text,
        analyzed_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY post_id (post_id),
        KEY ai_score (ai_score)
    ) {$charset_collate};";
    
    \dbDelta( $schema_blindspots );
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
    // ONLY AI/LLM bots - no traditional crawlers
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
        'googleagent-mariner' => 'GoogleAgent-Mariner',
        'gemini-deep-research' => 'Gemini-Deep-Research',
        'novaact'          => 'NovaAct',
        'devin'            => 'Devin',
        'linerbot'         => 'LinerBot',
        'qualifiedbot'     => 'QualifiedBot',
        'applebot-extended'=> 'Applebot-Extended',
        'meta-externalagent' => 'Meta-ExternalAgent',
        'meta-externalfetcher' => 'Meta-ExternalFetcher',
        'bytespider'       => 'Bytespider',
        'amazonbot'        => 'Amazonbot',
        'proratainc'       => 'ProRataInc',
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
        'GoogleAgent-Mariner',
        'Gemini-Deep-Research',
        'NovaAct',
        'Devin',
        'LinerBot',
        'QualifiedBot',
        'Applebot-Extended',
        'Meta-ExternalAgent',
        'Meta-ExternalFetcher',
        'Amazonbot',
        'ProRataInc',
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
        'GoogleAgent-Mariner' => 'google.com',
        'Gemini-Deep-Research' => 'gemini.google.com',
        'NovaAct'           => 'amazon.com',
        'Devin'             => 'devin.ai',
        'LinerBot'          => 'liner.com',
        'QualifiedBot'      => 'qualified.io',
        'Applebot-Extended' => 'apple.com',
        'Meta-ExternalAgent'=> 'meta.com',
        'Meta-ExternalFetcher'=> 'meta.com',
        'MistralAI-User'    => 'mistral.ai',
        'Amazonbot'         => 'amazon.com',
        'ProRataInc'        => 'prorata.ai',
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
    maybe_enqueue_frontend_styles();
    ob_start();
    ?>
    <div class="wpcs-llm">
        <table class="wpcs-table">
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
    maybe_enqueue_frontend_styles();
    ob_start();
    ?>
    <div class="wpcs-raw">
        <table class="wpcs-table">
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

    maybe_enqueue_frontend_styles();
    ob_start();
    ?>
    <ul class="wpcs-iplist">
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

    // Rocket glow color derived from bar end.
    $glow = hex_to_rgba( $bar_end, 0.7 );

    maybe_enqueue_frontend_styles();
    ob_start();
    ?>
    <div class="wpcs-bar-chart" role="img" aria-label="<?php echo \esc_attr( sprintf( /* translators: %s = window label */ \__( 'LLM bot hits (%s)', 'llm-bot-tracker-by-hueston' ), $label ) ); ?>" style="color:<?php echo \esc_attr( $text_col ); ?>;">
        <div class="wpcs-bar-chart-head">
            <?php echo \esc_html( sprintf( /* translators: %s = window label */ \__( 'LLM bot hits — %s', 'llm-bot-tracker-by-hueston' ), $label ) ); ?>
        </div>
        <div class="wpcs-bar-chart-body">
            <?php foreach ( $rows as $r ) :
                $name    = (string) $r->bot_name;
                $hits    = (int) $r->hits;
                $percent = max( 1, (int) floor( ( $hits / $max ) * 100 ) ); ?>
                <div class="wpcs-bar-row">
                    <div class="wpcs-bar-label">
                        <?php echo \esc_html( $name ); ?>
                    </div>
                    <div class="wpcs-bar-track" style="background:<?php echo \esc_attr( $track ); ?>;">
                        <div class="wpcs-bar-contrail" style="width:<?php echo (int) $percent; ?>%;background:linear-gradient(90deg, <?php echo \esc_attr( hex_to_rgba( $bar_start, 0.0 ) ); ?> 0%, <?php echo \esc_attr( hex_to_rgba( $bar_start, 0.35 ) ); ?> 55%, <?php echo \esc_attr( $bar_end ); ?> 100%);"></div>
                        <span class="wpcs-rocket" aria-hidden="true" style="left:<?php echo (int) $percent; ?>%;">
                            <svg viewBox="0 0 24 24" width="12" height="12" role="img" aria-hidden="true" focusable="false" style="filter: drop-shadow(0 0 3px <?php echo esc_attr( $glow ); ?>);">
                                <path fill="<?php echo esc_attr( $bar_end ); ?>" d="M2 21l5-2 9-9-3-3-9 9-2 5zm13.6-14.6l2 2 3.1-3.1c.5-.5.5-1.3 0-1.8L19 1.8c-.5-.5-1.3-.5-1.8 0l-3.1 3.1 1.5 1.5z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="wpcs-bar-value">
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
 * AI Blind Spots - Pages not visited by AI bots (v1.4.0)
 */

/**
 * Get cached AI discovery score or calculate if needed
 * 
 * @param int $post_id Post ID
 * @param bool $force_refresh Force recalculation
 * @return int Score from 0-100
 */
function get_cached_ai_score( $post_id, $force_refresh = false ) {
    global $wpdb;
    $analysis_table = $wpdb->prefix . 'wpcs_page_analysis';
    
    if ( ! $force_refresh ) {
        // Try to get cached score (less than 7 days old)
        $cached = $wpdb->get_row( $wpdb->prepare(
            "SELECT ai_score, analyzed_at FROM {$analysis_table} 
             WHERE post_id = %d 
             AND analyzed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $post_id
        ) );
        
        if ( $cached ) {
            return (int) $cached->ai_score;
        }
    }
    
    // Calculate fresh score
    $score = calculate_ai_discovery_score( $post_id );
    
    // Store in cache table
    $wpdb->replace(
        $analysis_table,
        array(
            'post_id' => $post_id,
            'ai_score' => $score,
            'analyzed_at' => current_time( 'mysql' )
        ),
        array( '%d', '%d', '%s' )
    );
    
    return $score;
}

/**
 * Get pages not visited by AI bots (optimized with pagination and caching)
 * 
 * @param int $days Number of days to look back
 * @param int $page Current page number
 * @param int $per_page Items per page
 * @param bool $force_refresh Force refresh of cache
 * @return array Array with pages and pagination info
 */
function get_ai_ignored_pages( $days = 30, $page = 1, $per_page = 50, $force_refresh = false ) {
    global $wpdb;
    
    // Check cache first (unless force refresh)
    $cache_key = 'wpcs_ai_blindspots_' . $days . '_days';
    if ( ! $force_refresh ) {
        $cached_data = \get_transient( $cache_key );
        if ( false !== $cached_data ) {
            // Apply pagination to cached results
            $total = count( $cached_data );
            $offset = ( $page - 1 ) * $per_page;
            $paged_results = array_slice( $cached_data, $offset, $per_page );
            
            return array(
                'pages' => $paged_results,
                'total' => $total,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => ceil( $total / $per_page )
            );
        }
    }
    
    // Use WordPress functions for getting posts
    $args = array(
        'post_type'      => array( 'post', 'page' ),
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids'
    );
    
    // Allow filtering of post types
    $args = \apply_filters( 'wpcs_blindspot_post_types', $args );
    
    $all_posts = \get_posts( $args );
    
    if ( empty( $all_posts ) ) {
        return array(
            'pages' => array(),
            'total' => 0,
            'per_page' => $per_page,
            'current_page' => $page,
            'total_pages' => 0
        );
    }
    
    // Get visited URLs using prepared statement
    $table_name = $wpdb->prefix . 'wpcs_requests';
    $llm_bots = get_llm_bot_labels();
    
    if ( empty( $llm_bots ) ) {
        return array(
            'pages' => array(),
            'total' => 0,
            'per_page' => $per_page,
            'current_page' => $page,
            'total_pages' => 0
        );
    }
    
    $placeholders = implode( ',', array_fill( 0, count( $llm_bots ), '%s' ) );
    
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $query = $wpdb->prepare(
        "SELECT DISTINCT url_path 
         FROM {$table_name} 
         WHERE bot_name IN ({$placeholders})
         AND hit_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
        array_merge( $llm_bots, array( $days ) )
    );
    // phpcs:enable
    
    // Get visited URLs (no need for transient, query is fast enough)
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $visited_urls = $wpdb->get_col( $query );
    
    // Find ignored pages
    $ignored_pages = array();
    
    foreach ( $all_posts as $post_id ) {
        $permalink = \get_permalink( $post_id );
        $path = \wp_parse_url( $permalink, PHP_URL_PATH );
        
        if ( ! in_array( $path, $visited_urls, true ) ) {
            $post = \get_post( $post_id );
            
            if ( $post ) {
                // Use cached AI score
                $ai_score = get_cached_ai_score( $post_id, $force_refresh );
                
                // Build simplified page data
                $ignored_pages[] = array(
                    'id' => $post_id,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'date' => $post->post_date,
                    'permalink' => $permalink,
                    'ai_score' => $ai_score
                );
            }
        }
    }
    
    // Sort by AI score (lowest first - needs most attention)
    usort( $ignored_pages, function( $a, $b ) {
        return $a['ai_score'] - $b['ai_score'];
    } );
    
    // Cache the full results for 6 hours
    \set_transient( $cache_key, $ignored_pages, 6 * HOUR_IN_SECONDS );
    
    // Apply pagination
    $total = count( $ignored_pages );
    $offset = ( $page - 1 ) * $per_page;
    $paged_results = array_slice( $ignored_pages, $offset, $per_page );
    
    return array(
        'pages' => $paged_results,
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $page,
        'total_pages' => ceil( $total / $per_page )
    );
}

/**
 * Calculate AI Discovery Score (optimized - removed expensive queries)
 * 
 * @param int $post_id Post ID to analyze
 * @return int Score from 0-100
 */
function calculate_ai_discovery_score( $post_id ) {
    $score = 100;
    
    // Validate post ID
    if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
        return 0;
    }
    
    // Check if post exists
    $post = \get_post( $post_id );
    if ( ! $post ) {
        return 0;
    }
    
    // Check content length
    $content = $post->post_content;
    $word_count = str_word_count( \wp_strip_all_tags( $content ) );
    
    if ( $word_count < 100 ) {
        $score -= 30;  // Very short content
    } elseif ( $word_count < 300 ) {
        $score -= 20;  // Short content
    } elseif ( $word_count < 500 ) {
        $score -= 10;  // Below average content
    }
    
    // Check for title
    if ( empty( $post->post_title ) || strlen( $post->post_title ) < 10 ) {
        $score -= 15;
    }
    
    // Check for excerpt
    if ( empty( $post->post_excerpt ) ) {
        $score -= 10;
    }
    
    // Check if page is in menu (likely important)
    $menu_items = \wp_get_nav_menu_items( \get_nav_menu_locations() );
    if ( is_array( $menu_items ) ) {
        foreach ( $menu_items as $item ) {
            if ( isset( $item->object_id ) && $item->object_id == $post_id ) {
                $score += 10;  // Bonus for being in navigation
                break;
            }
        }
    }
    
    // Check for featured image
    if ( ! \has_post_thumbnail( $post_id ) ) {
        $score -= 5;
    }
    
    // Check for meta description (Yoast SEO) - only if available
    if ( function_exists( '\\WPSEO_Meta::get_value' ) ) {
        $meta_desc = \WPSEO_Meta::get_value( 'metadesc', $post_id );
        if ( empty( $meta_desc ) ) {
            $score -= 15;
        }
        
        $noindex = \WPSEO_Meta::get_value( 'meta-robots-noindex', $post_id );
        if ( $noindex === '1' ) {
            $score -= 40;  // Noindex is a strong signal
        }
    }
    
    // Check post age (older posts might be less relevant)
    $post_age_days = ( time() - strtotime( $post->post_date ) ) / DAY_IN_SECONDS;
    if ( $post_age_days > 365 ) {
        $score -= 5;  // Over a year old
    }
    
    // Allow other plugins to modify score
    $score = \apply_filters( 'wpcs_ai_discovery_score', $score, $post_id );
    
    return max( 0, min( 100, $score ) );
}

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

/**
 * Enqueue admin styles
 */
\add_action( 'admin_enqueue_scripts', function ( $hook ) {
    // Only enqueue on our admin page
    if ( $hook !== 'tools_page_wpcs-logs' ) {
        return;
    }
    
    \wp_enqueue_style(
        'llm-bot-tracker-admin',
        \plugins_url( 'assets/css/admin.css', __FILE__ ),
        [],
        VERSION
    );
} );

/**
 * Enqueue frontend styles for shortcodes
 */
\add_action( 'wp_enqueue_scripts', function () {
    // Register the style
    \wp_register_style(
        'llm-bot-tracker-frontend',
        \plugins_url( 'assets/css/frontend.css', __FILE__ ),
        [],
        VERSION
    );
} );

/**
 * Helper to ensure frontend styles are enqueued when shortcode is used
 */
function maybe_enqueue_frontend_styles(): void {
    if ( ! \wp_style_is( 'llm-bot-tracker-frontend', 'enqueued' ) ) {
        \wp_enqueue_style( 'llm-bot-tracker-frontend' );
    }
}

// Hide unrelated admin notices on our Tools page to reduce noise.
\add_action( 'admin_notices', function () {
    $screen = function_exists( '\\get_current_screen' ) ? \get_current_screen() : null;
    if ( $screen && isset( $screen->id ) && $screen->id === 'tools_page_wpcs-logs' ) {
        \remove_all_actions( 'admin_notices' );
        \remove_all_actions( 'all_admin_notices' );
    }
}, 1 );

/**
 * Render admin page for logs with filtering and deletion controls.
 * v1.4.0 - Added tabbed interface
 */
function render_wpcs_admin_logs_page(): void {
    \current_user_can( 'manage_options' ) || \wp_die( \esc_html__( 'Unauthorized.', 'llm-bot-tracker-by-hueston' ) );

    // Get current tab
    $current_tab = isset( $_GET['tab'] ) ? \sanitize_text_field( $_GET['tab'] ) : 'logs';
    
    ?>
    <div class="wrap">
        <?php
        $logo_url = plugins_url( 'images/hueston-llmo-logo.png', __FILE__ );
        ?>
        <div class="wpcs-admin-header">
            <a href="https://hueston.co" target="_blank" rel="noopener noreferrer">
                <img src="<?php echo \esc_url( $logo_url ); ?>" alt="<?php echo \esc_attr__( 'Hueston LLM', 'llm-bot-tracker-by-hueston' ); ?>" class="wpcs-logo" />
            </a>
            <h1><?php echo \esc_html__( 'LLM Bot Tracker', 'llm-bot-tracker-by-hueston' ); ?></h1>
        </div>
        
        <nav class="nav-tab-wrapper">
            <a href="?page=wpcs-logs&tab=logs" class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                <?php echo \esc_html__( 'Crawler Logs', 'llm-bot-tracker-by-hueston' ); ?>
            </a>
            <a href="?page=wpcs-logs&tab=blindspots" class="nav-tab <?php echo $current_tab === 'blindspots' ? 'nav-tab-active' : ''; ?>">
                <?php echo \esc_html__( 'AI Blind Spots', 'llm-bot-tracker-by-hueston' ); ?>
                <span class="update-plugins" style="background:#D4AF37;color:#0A1F44;"><span style="font-size:11px;">NEW</span></span>
            </a>
        </nav>
        
        <div class="tab-content">
            <?php
            if ( $current_tab === 'blindspots' ) {
                render_blindspots_tab_content();
            } else {
                render_logs_tab_content();
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Render the crawler logs tab content
 */
function render_logs_tab_content(): void {

    global $wpdb;
    $requests_table = $wpdb->prefix . 'wpcs_requests';

    // Read filters (GET for view; also read from POST for delete actions).
    $get = function( string $key ): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters
        return isset( $_GET[ $key ] ) ? \sanitize_text_field( \wp_unslash( $_GET[ $key ] ) ) : '';
    };
    $post = function( string $key ): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified before this function is used
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

    // The wrap and header are now in the main render_wpcs_admin_logs_page function
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

    echo '<div class="wpcs-admin-summary">';
    echo '<div class="wpcs-total">' . \esc_html__( 'Total', 'llm-bot-tracker-by-hueston' ) . ': ' . \esc_html( number_format_i18n( $total_all ) ) . '</div>';
    echo '<div class="wpcs-legend">';
    echo '<span><span style="background:#D4AF37;"></span><span>7d ' . \esc_html( number_format_i18n( $total_7 ) ) . '</span></span>';
    echo '<span><span style="background:#0A1F44;"></span><span>30d ' . \esc_html( number_format_i18n( $total_30 ) ) . '</span></span>';
    echo '<span><span style="background:#6b7280;"></span><span>90d ' . \esc_html( number_format_i18n( $total_90 ) ) . '</span></span>';
    echo '</div>';
    echo '</div>';

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
            echo '<div class="wpcs-topbots-value">' . \esc_html( number_format_i18n( $c ) ) . '</div>';
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
    echo '<form method="get" action="" class="wpcs-filter-form">';
    echo '<input type="hidden" name="page" value="wpcs-logs" />';
    echo '<input type="hidden" name="tab" value="logs" />';
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
    echo '<label>' . \esc_html__( 'Per page', 'llm-bot-tracker-by-hueston' ) . ' <input type="number" min="10" max="200" name="per_page" value="' . (int) $per_page . '" class="wpcs-per-page" /></label>';
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
                'tab'      => 'logs',
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
        'tab'      => 'logs',
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
        echo '<span class="wpcs-page-info">' . (int) $paged . ' / ' . (int) $total_pages . '</span> ';
        echo '<a class="button" href="' . \esc_url( add_query_arg( 'paged', $next, $base_url ) ) . '">' . \esc_html__( 'Next', 'llm-bot-tracker-by-hueston' ) . ' »</a>';
    }
    echo '</div></div>';

    echo '</form>';
}

/**
 * Render the AI Blind Spots tab content (optimized with pagination)
 * v1.4.0 - New feature
 */
function render_blindspots_tab_content(): void {
    // Get parameters
    $days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
    $current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
    $force_refresh = isset( $_GET['refresh'] ) && $_GET['refresh'] === '1';
    
    // Get paginated data
    $result = get_ai_ignored_pages( $days, $current_page, 50, $force_refresh );
    $ignored_pages = $result['pages'];
    $ignored_total = $result['total'];
    $total_pages_count = $result['total_pages'];
    
    // Get total published posts/pages for stats
    $total_published = \wp_count_posts( 'post' )->publish + \wp_count_posts( 'page' )->publish;
    $coverage_percent = $total_published > 0 ? round( ( ( $total_published - $ignored_total ) / $total_published ) * 100 ) : 0;
    
    // Show refresh success message
    if ( $force_refresh ) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo \esc_html__( 'Analysis cache refreshed successfully!', 'llm-bot-tracker-by-hueston' );
        echo '</p></div>';
    }
    
    ?>
    <style>
        .wpcs-blindspot-stats { display: flex; gap: 20px; margin: 20px 0; }
        .wpcs-blindspot-stats .card { flex: 1; padding: 20px; background: #fff; border: 1px solid #ddd; text-align: center; border-radius: 8px; }
        .wpcs-blindspot-stats h2 { font-size: 32px; margin: 0; color: #D4AF37; }
        .wpcs-score-bar { width: 100px; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden; display: inline-block; }
        .wpcs-score-fill { height: 100%; background: linear-gradient(90deg, #0A1F44, #D4AF37); color: #fff; text-align: center; font-size: 11px; line-height: 20px; }
        .wpcs-filter-bar { background: #fff; padding: 15px; border: 1px solid #ddd; margin: 20px 0; display: flex; align-items: center; gap: 20px; }
        .wpcs-refresh-btn { margin-left: auto; }
    </style>
    
    <p><?php echo \esc_html__( 'Pages on your site that have not been visited by AI/LLM bots. Lower AI scores indicate pages that need optimization for better AI discovery.', 'llm-bot-tracker-by-hueston' ); ?></p>
    
    <div class="wpcs-blindspot-stats">
        <div class="card">
            <h2><?php echo \esc_html( number_format_i18n( $ignored_total ) ); ?></h2>
            <p><?php echo \esc_html__( 'Pages Invisible to AI', 'llm-bot-tracker-by-hueston' ); ?></p>
        </div>
        <div class="card">
            <h2><?php echo \esc_html( $coverage_percent ); ?>%</h2>
            <p><?php echo \esc_html__( 'AI Coverage Rate', 'llm-bot-tracker-by-hueston' ); ?></p>
        </div>
        <div class="card">
            <h2><?php echo \esc_html( number_format_i18n( $total_published ) ); ?></h2>
            <p><?php echo \esc_html__( 'Total Published Pages', 'llm-bot-tracker-by-hueston' ); ?></p>
        </div>
    </div>
    
    <div class="wpcs-filter-bar">
        <form method="get" action="" style="display: flex; align-items: center; gap: 10px;">
            <input type="hidden" name="page" value="wpcs-logs" />
            <input type="hidden" name="tab" value="blindspots" />
            <label><?php echo \esc_html__( 'Time Period:', 'llm-bot-tracker-by-hueston' ); ?>
                <select name="days" onchange="this.form.submit()">
                    <option value="7" <?php selected( $days, 7 ); ?>><?php echo \esc_html__( 'Last 7 days', 'llm-bot-tracker-by-hueston' ); ?></option>
                    <option value="30" <?php selected( $days, 30 ); ?>><?php echo \esc_html__( 'Last 30 days', 'llm-bot-tracker-by-hueston' ); ?></option>
                    <option value="90" <?php selected( $days, 90 ); ?>><?php echo \esc_html__( 'Last 90 days', 'llm-bot-tracker-by-hueston' ); ?></option>
                    <option value="365" <?php selected( $days, 365 ); ?>><?php echo \esc_html__( 'Last year', 'llm-bot-tracker-by-hueston' ); ?></option>
                </select>
            </label>
        </form>
        <div class="wpcs-refresh-btn">
            <a href="<?php echo \esc_url( add_query_arg( array( 
                'page' => 'wpcs-logs', 
                'tab' => 'blindspots', 
                'days' => $days, 
                'refresh' => '1' 
            ), admin_url( 'tools.php' ) ) ); ?>" class="button button-secondary">
                <?php echo \esc_html__( '🔄 Refresh Analysis', 'llm-bot-tracker-by-hueston' ); ?>
            </a>
            <span class="description" style="margin-left: 10px;">
                <?php echo \esc_html__( 'Cached for 6 hours', 'llm-bot-tracker-by-hueston' ); ?>
            </span>
        </div>
    </div>
    
    <?php if ( ! empty( $ignored_pages ) ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 35%;"><?php echo \esc_html__( 'Title', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th style="width: 10%;"><?php echo \esc_html__( 'Type', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th style="width: 15%;"><?php echo \esc_html__( 'AI Score', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th style="width: 15%;"><?php echo \esc_html__( 'Published', 'llm-bot-tracker-by-hueston' ); ?></th>
                    <th style="width: 25%;"><?php echo \esc_html__( 'Actions', 'llm-bot-tracker-by-hueston' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $ignored_pages as $page ) : ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo \esc_url( \get_edit_post_link( $page['id'] ) ); ?>">
                                    <?php echo \esc_html( $page['title'] ?: '(no title)' ); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo \esc_url( $page['permalink'] ); ?>" target="_blank">
                                        <?php echo \esc_html__( 'View', 'llm-bot-tracker-by-hueston' ); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo \esc_html( ucfirst( $page['type'] ) ); ?></td>
                        <td>
                            <div class="wpcs-score-bar">
                                <div class="wpcs-score-fill" style="width: <?php echo (int) $page['ai_score']; ?>%;">
                                    <?php echo (int) $page['ai_score']; ?>%
                                </div>
                            </div>
                        </td>
                        <td><?php echo \esc_html( \human_time_diff( strtotime( $page['date'] ), current_time( 'timestamp' ) ) ); ?> ago</td>
                        <td>
                            <a href="<?php echo \esc_url( \get_edit_post_link( $page['id'] ) ); ?>" class="button button-small">
                                <?php echo \esc_html__( 'Optimize', 'llm-bot-tracker-by-hueston' ); ?>
                            </a>
                            <a href="<?php echo \esc_url( $page['permalink'] ); ?>" target="_blank" class="button button-small">
                                <?php echo \esc_html__( 'View', 'llm-bot-tracker-by-hueston' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php 
        // Pagination controls
        if ( $total_pages_count > 1 ) : 
            $base_url = add_query_arg( array(
                'page' => 'wpcs-logs',
                'tab' => 'blindspots',
                'days' => $days
            ), admin_url( 'tools.php' ) );
        ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php echo sprintf( 
                            \esc_html__( '%d items', 'llm-bot-tracker-by-hueston' ), 
                            $ignored_total 
                        ); ?>
                    </span>
                    
                    <?php if ( $current_page > 1 ) : ?>
                        <a class="button" href="<?php echo \esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">«</a>
                        <a class="button" href="<?php echo \esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>">‹</a>
                    <?php endif; ?>
                    
                    <span class="paging-input">
                        <?php echo sprintf( 
                            \esc_html__( 'Page %d of %d', 'llm-bot-tracker-by-hueston' ),
                            $current_page,
                            $total_pages_count
                        ); ?>
                    </span>
                    
                    <?php if ( $current_page < $total_pages_count ) : ?>
                        <a class="button" href="<?php echo \esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>">›</a>
                        <a class="button" href="<?php echo \esc_url( add_query_arg( 'paged', $total_pages_count, $base_url ) ); ?>">»</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else : ?>
        <div class="notice notice-success">
            <p><?php echo \esc_html__( '🎉 Great! All your published pages have been visited by AI bots in this time period.', 'llm-bot-tracker-by-hueston' ); ?></p>
        </div>
    <?php endif; ?>
    <?php
}

