<?php
/*
Plugin Name: Joburg Meet-up Quiz
Description: An interactive quiz plugin with leaderboard functionality
Version: 1.2.0
Author: Distinct
Author URI: https://distinct.africa
Requires at least: 6.0
Tested up to: 6.7.1
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

require_once plugin_dir_path(__FILE__) . 'admin/quiz-questions.php';

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Plugin activation hook
register_activation_hook(__FILE__, 'distinct_jhb_quiz_activate');

function distinct_jhb_quiz_activate()
{

    function distinct_jhb_quiz_init_settings() {
        add_option('distinct_jhb_quiz_question_timer', 20);
        add_option('distinct_jhb_quiz_bonus_timer', 30);
        add_option('distinct_jhb_quiz_bonus_score', 10);
        add_option('distinct_jhb_quiz_lives', 3);
    }
    register_activation_hook(__FILE__, 'distinct_jhb_quiz_init_settings');

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Create results table for all attempts
    $sql_results = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}quiz_results (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        player_id varchar(100) NOT NULL,
        first_name varchar(50) NOT NULL,
        last_name varchar(50) NOT NULL,
        score int NOT NULL,
        completion_time datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY player_score (player_id, score)
    ) $charset_collate;";

    // Create table for player statistics
    $sql_stats = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}quiz_player_stats (
        player_id varchar(100) NOT NULL,
        first_name varchar(50) NOT NULL,
        last_name varchar(50) NOT NULL,
        highest_score int NOT NULL DEFAULT 0,
        average_score float NOT NULL DEFAULT 0,
        total_attempts int NOT NULL DEFAULT 0,
        last_attempt_score int NOT NULL DEFAULT 0,
        last_attempt_time datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (player_id),
        KEY highest_score (highest_score)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Execute each CREATE TABLE query separately
    dbDelta($sql_results);
    dbDelta($sql_stats);

    // Set up cache group
    wp_cache_add_global_groups('distinct_jhb_quiz');
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'distinct_jhb_quiz_enqueue_scripts');

// Add a cache cleanup function for deactivation
register_deactivation_hook(__FILE__, 'distinct_jhb_quiz_deactivate');

function distinct_jhb_quiz_deactivate()
{
    // Clean up all plugin-related caches
    wp_cache_flush();
}

function distinct_jhb_quiz_enqueue_scripts()
{
    // Get plugin data for version tracking
    $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), 'plugin');
    $version = $plugin_data['Version'] ? $plugin_data['Version'] : '1.0';

    // Enqueue style with version parameter
    wp_enqueue_style(
        'distinct-jhb-quiz-style',
        plugins_url('css/quiz-style.css', __FILE__),
        array(),  // no dependencies
        $version  // add version number for cache busting
    );

    wp_enqueue_script(
        'distinct-jhb-quiz-script',
        plugins_url('js/quiz-script.js', __FILE__),
        array('jquery'),
        $version,  // use same version for script
        true
    );

    // Pass data to JavaScript with new settings
    wp_localize_script('distinct-jhb-quiz-script', 'wpQuizGame', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('distinct_jhb_quiz_nonce'),
        'questions' => get_quiz_questions(),
        'settings' => array(
            'questionTimer' => get_option('distinct_jhb_quiz_question_timer', 20),
            'bonusTimer' => get_option('distinct_jhb_quiz_bonus_timer', 30),
            'bonusScore' => get_option('distinct_jhb_quiz_bonus_score', 10),
            'lives' => get_option('distinct_jhb_quiz_lives', 3)
        )
    ));
}

// Remove the general wp_enqueue_scripts action
remove_action('wp_enqueue_scripts', 'distinct_jhb_quiz_enqueue_scripts');



function distinct_jhb_quiz_settings_page() {
    // If settings are being saved
    if (isset($_POST['update_quiz_settings']) && check_admin_referer('distinct_jhb_quiz_settings')) {
        update_option('distinct_jhb_quiz_leaderboard_range', sanitize_text_field($_POST['distinct_jhb_quiz_leaderboard_range']));
        update_option('distinct_jhb_quiz_question_timer', absint($_POST['distinct_jhb_quiz_question_timer']));
        update_option('distinct_jhb_quiz_bonus_timer', absint($_POST['distinct_jhb_quiz_bonus_timer']));
        update_option('distinct_jhb_quiz_bonus_score', absint($_POST['distinct_jhb_quiz_bonus_score']));
        update_option('distinct_jhb_quiz_lives', absint($_POST['distinct_jhb_quiz_lives']));
        
        echo '<div class="updated"><p>Settings saved successfully!</p></div>';
    }

    // Get current settings
    $current_range = get_option('distinct_jhb_quiz_leaderboard_range', 'all');
    $question_timer = get_option('distinct_jhb_quiz_question_timer', 20);
    $bonus_timer = get_option('distinct_jhb_quiz_bonus_timer', 30);
    $bonus_score = get_option('distinct_jhb_quiz_bonus_score', 10);
    $lives = get_option('distinct_jhb_quiz_lives', 3);
    ?>
    <div class="wrap">
        <h1>Quiz Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field('distinct_jhb_quiz_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Leaderboard Date Range</th>
                    <td>
                        <select name="distinct_jhb_quiz_leaderboard_range">
                            <option value="day" <?php selected($current_range, 'day'); ?>>Today Only</option>
                            <option value="week" <?php selected($current_range, 'week'); ?>>This Week</option>
                            <option value="month" <?php selected($current_range, 'month'); ?>>This Month</option>
                            <option value="all" <?php selected($current_range, 'all'); ?>>All Time</option>
                        </select>
                        <p class="description">Select the time period for which the leaderboard should display scores.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Question Timer (seconds)</th>
                    <td>
                        <input type="number" name="distinct_jhb_quiz_question_timer" 
                               value="<?php echo esc_attr($question_timer); ?>" min="5" max="60" />
                        <p class="description">How many seconds players have to answer each question.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Bonus Game Timer (seconds)</th>
                    <td>
                        <input type="number" name="distinct_jhb_quiz_bonus_timer" 
                               value="<?php echo esc_attr($bonus_timer); ?>" min="10" max="60" />
                        <p class="description">Duration of the Whack-a-Wapuu bonus game.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Bonus Score Per Hit</th>
                    <td>
                        <input type="number" name="distinct_jhb_quiz_bonus_score" 
                               value="<?php echo esc_attr($bonus_score); ?>" min="1" max="50" />
                        <p class="description">Points awarded for each successful Wapuu hit.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Player Lives</th>
                    <td>
                        <input type="number" name="distinct_jhb_quiz_lives" 
                               value="<?php echo esc_attr($lives); ?>" min="1" max="5" />
                        <p class="description">Number of lives players start with.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="update_quiz_settings" class="button-primary" 
                       value="<?php _e('Save Settings', 'distinct-jhb-quiz'); ?>" />
            </p>
        </form>
    </div>
    <?php
}




// Modify the leaderboard query to respect the date range setting
function distinct_jhb_quiz_get_date_range_condition()
{
    global $wpdb;
    $range = get_option('distinct_jhb_quiz_leaderboard_range', 'all');
    $current_time = current_time('mysql');

    switch ($range) {
        case 'day':
            return $wpdb->prepare(
                "last_attempt_time >= %s AND last_attempt_time < %s",
                array(
                    gmdate('Y-m-d 00:00:00', strtotime($current_time)),
                    gmdate('Y-m-d 23:59:59', strtotime($current_time))
                )
            );
        case 'week':
            return $wpdb->prepare(
                "YEARWEEK(last_attempt_time, 1) = YEARWEEK(%s, 1)",
                $current_time
            );
        case 'month':
            return $wpdb->prepare(
                "YEAR(last_attempt_time) = YEAR(%s) AND MONTH(last_attempt_time) = MONTH(%s)",
                $current_time,
                $current_time
            );
        default:
            return $wpdb->prepare("1=%d", 1); // Properly prepared, even for a constant condition
    }
}

// Add shortcodes for quiz and leaderboard
add_shortcode('distinct_jhb_quiz', 'distinct_jhb_quiz_shortcode');
add_shortcode('distinct_jhb_leaderboard', 'distinct_jhb_leaderboard_shortcode');

function distinct_jhb_quiz_db_operation($cache_key, $callback, $cache_group = 'distinct_jhb_quiz', $cache_time = 300) {
    $result = wp_cache_get($cache_key, $cache_group);
    
    if (false === $result) {
        $result = $callback();
        if ($result !== false) {
            wp_cache_set($cache_key, $result, $cache_group, $cache_time);
        }
    }
    
    return $result;
}

// Update the leaderboard shortcode function
function distinct_jhb_leaderboard_shortcode()
{
    global $wpdb;
    $date_condition = distinct_jhb_quiz_get_date_range_condition();
    
    // Create a unique cache key based on the date condition
    $cache_key = 'quiz_leaderboard_' . md5($date_condition);
    
    // Try to get cached leaderboard data
    $leaderboard = wp_cache_get($cache_key, 'distinct_jhb_quiz');
    
    if (false === $leaderboard) {
        // The date condition is already prepared from distinct_jhb_quiz_get_date_range_condition()
        $leaderboard = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    first_name,
                    last_name,
                    highest_score,
                    ROUND(average_score, 1) as average_score,
                    last_attempt_score,
                    total_attempts,
                    last_attempt_time
                FROM {$wpdb->prefix}quiz_player_stats 
                WHERE %s  
                ORDER BY highest_score DESC, average_score DESC",
                // Remove the prepare() from the condition since we're preparing it here
                str_replace('%', '%%', $date_condition)
            )
        );
        
        // Cache the results
        wp_cache_set($cache_key, $leaderboard, 'distinct_jhb_quiz', 300);
    }
    // Get the current range setting for display
    $range_text = array(
        'day' => 'Today',
        'week' => 'This Week',
        'month' => 'This Month',
        'all' => 'All Time'
    )[get_option('distinct_jhb_quiz_leaderboard_range', 'all')];

    // Enqueue scripts only when shortcode is used
    $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), 'plugin');
    $version = $plugin_data['Version'] ? $plugin_data['Version'] : '1.0';

    wp_enqueue_style(
        'distinct-jhb-quiz-style',
        plugins_url('css/quiz-style.css', __FILE__),
        array(),
        $version
    );
    

    ob_start();
?>
    <div class="wp-quiz-leaderboard">
        <h2>WordPress Quiz Leaderboard (<?php echo esc_html($range_text); ?>)</h2>
        <div class="leaderboard-table-container">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <th>High Score</th>
                        <th>Average</th>
                        <th>Recent</th>
                        <th>Attempts</th>
                        <th>Last Played</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaderboard)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No scores recorded for this time period yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaderboard as $index => $player): ?>
                            <tr>
                                <td class="rank"><?php echo esc_html($index + 1); ?></td>
                                <td class="player-name">
                                    <?php echo esc_html($player->first_name . ' ' . $player->last_name); ?>
                                </td>
                                <td class="highest-score">
                                    <?php echo esc_html(number_format($player->highest_score)); ?>
                                </td>
                                <td class="average-score">
                                    <?php echo esc_html(number_format($player->average_score, 1)); ?>
                                </td>
                                <td class="recent-score">
                                    <?php echo esc_html(number_format($player->last_attempt_score)); ?>
                                </td>
                                <td class="attempts">
                                    <?php echo esc_html(number_format($player->total_attempts)); ?>
                                </td>
                                <td class="last-played">
                                    <?php echo esc_html(human_time_diff(strtotime($player->last_attempt_time), current_time('timestamp'))); ?> ago
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
    return ob_get_clean();
}

function distinct_jhb_quiz_shortcode()
{
    // Enqueue scripts only when shortcode is used
    $plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), 'plugin');
    $version = $plugin_data['Version'] ? $plugin_data['Version'] : '1.0';

    wp_enqueue_style(
        'distinct-jhb-quiz-style',
        plugins_url('css/quiz-style.css', __FILE__),
        array(),
        $version
    );

    wp_enqueue_script(
        'distinct-jhb-quiz-script',
        plugins_url('js/quiz-script.js', __FILE__),
        array('jquery'),
        $version,
        true
    );

    // Pass data to JavaScript with all settings
    wp_localize_script('distinct-jhb-quiz-script', 'wpQuizGame', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('distinct_jhb_quiz_nonce'),
        'questions' => get_quiz_questions(),
        'settings' => array(
            'questionTimer' => get_option('distinct_jhb_quiz_question_timer', 20),
            'bonusTimer' => get_option('distinct_jhb_quiz_bonus_timer', 30),
            'bonusScore' => get_option('distinct_jhb_quiz_bonus_score', 10),
            'lives' => get_option('distinct_jhb_quiz_lives', 3)
        )
    ));
    
    ob_start();
?>
    <div id="wp-quiz-game-container" class="wp-quiz-container">
        <div id="quiz-start-screen" class="quiz-screen active">
            <h2>WordPress Quiz</h2>
            <div class="name-inputs">
                <input type="text" id="first-name" placeholder="First Name" />
                <input type="text" id="last-name" placeholder="Last Name" />
            </div>
            <p class="description">Your name and surname are used to uniquely identify you and are displayed publically on the <a href="<?php echo esc_url(site_url('/leaderboard/')); ?>">quiz leaderboard</a>.</p>
            <button id="start-quiz" class="quiz-button">Begin</button><br><br>
            <a href="<?php echo esc_url(site_url('/leaderboard/')); ?>">View Leaderboard</a>
        </div>
        <div id="quiz-game-screen" class="quiz-screen">
            <!-- Quiz content will be dynamically inserted here -->
        </div>
        <div id="bonus-game-screen" class="quiz-screen">
            <h2>Bonus Round: Whack-a-Wapuu!</h2>
            <div class="bonus-info">
                <div id="bonus-timer">Time: 30s</div>
                <div id="bonus-score">Bonus Score: 0</div>
            </div>
            <div class="mole-container">
                <div class="mole-row">
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                </div>
                <div class="mole-row">
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                </div>
                <div class="mole-row">
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                </div>
                <div class="mole-row">
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                    <div class="mole-hole">
                        <div class="mole"></div>
                    </div>
                </div>
            </div>
        </div>
        <div id="quiz-end-screen" class="quiz-screen">
            <h2>Game Complete!</h2>
            <div id="final-score"></div>
            <button id="restart-quiz" class="quiz-button">Play Again</button><br><br>
            <a href="<?php echo esc_url(site_url('/leaderboard/')); ?>">View Leaderboard</a>
        </div>
    </div>
<?php
    return ob_get_clean();
}

// Handle AJAX requests
add_action('wp_ajax_save_quiz_stats', 'distinct_jhb_quiz_save_stats');
add_action('wp_ajax_nopriv_save_quiz_stats', 'distinct_jhb_quiz_save_stats');

function distinct_jhb_quiz_save_stats() {
    check_ajax_referer('distinct_jhb_quiz_nonce', 'nonce');

    if (!isset($_POST['player_id'], $_POST['first_name'], $_POST['last_name'], $_POST['score'])) {
        wp_send_json_error(array('message' => 'Missing required fields'));
        return;
    }

    global $wpdb;
    
    // Properly unslash and sanitize input
    $player_id = sanitize_text_field(wp_unslash($_POST['player_id']));
    $first_name = sanitize_text_field(wp_unslash($_POST['first_name']));
    $last_name = sanitize_text_field(wp_unslash($_POST['last_name']));
    $score = absint(wp_unslash($_POST['score']));

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Insert new result using callback
        $insert_result = distinct_jhb_quiz_db_operation(
            'quiz_insert_' . $player_id,
            function() use ($wpdb, $player_id, $first_name, $last_name, $score) {
                return $wpdb->insert(
                    $wpdb->prefix . 'quiz_results',
                    array(
                        'player_id' => $player_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'score' => $score
                    ),
                    array('%s', '%s', '%s', '%d')
                );
            }
        );

        // Get player stats using callback
        $stats = distinct_jhb_quiz_db_operation(
            'quiz_player_stats_' . $player_id,
            function() use ($wpdb, $player_id) {
                return $wpdb->get_row($wpdb->prepare(
                    "SELECT 
                        COUNT(*) as total_attempts,
                        MAX(score) as highest_score,
                        AVG(score) as average_score
                    FROM {$wpdb->prefix}quiz_results 
                    WHERE player_id = %s",
                    $player_id
                ));
            }
        );

        // Update player statistics
        $update_result = distinct_jhb_quiz_db_operation(
            'quiz_update_' . $player_id,
            function() use ($wpdb, $player_id, $first_name, $last_name, $score, $stats) {
                return $wpdb->replace(
                    $wpdb->prefix . 'quiz_player_stats',
                    array(
                        'player_id' => $player_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'highest_score' => max($stats->highest_score, $score),
                        'average_score' => $stats->average_score,
                        'total_attempts' => $stats->total_attempts,
                        'last_attempt_score' => $score,
                        'last_attempt_time' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%d', '%f', '%d', '%d', '%s')
                );
            }
        );

        $wpdb->query('COMMIT');

        // Clear caches
        wp_cache_delete('quiz_player_stats_' . $player_id, 'distinct_jhb_quiz');
        wp_cache_delete('quiz_leaderboard_' . md5(distinct_jhb_quiz_get_date_range_condition()), 'distinct_jhb_quiz');

        wp_send_json_success(array('message' => 'Score saved successfully'));
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(array('message' => 'Error saving score'));
    }
}
// Add admin menu
add_action('admin_menu', 'distinct_jhb_quiz_admin_menu');

// Modify the existing admin menu function
function distinct_jhb_quiz_admin_menu()
{
    // Add main menu page
    add_menu_page(
        'WordPress JHB Quiz',
        'JHB Quiz',
        'manage_options',
        'distinct-jhb-quiz',
        'distinct_jhb_quiz_admin_page',
        'dashicons-games'
    );

    // Add settings submenu
    add_submenu_page(
        'distinct-jhb-quiz',                // Parent slug
        'Leaderboard Settings',       // Page title
        'Settings',                   // Menu title
        'manage_options',             // Capability
        'distinct-jhb-quiz-settings',       // Menu slug
        'distinct_jhb_quiz_settings_page'   // Function
    );

    // Register settings
    register_setting('distinct_jhb_quiz_options', 'distinct_jhb_quiz_leaderboard_range');
}

function distinct_jhb_quiz_admin_page()
{
    $results = distinct_jhb_quiz_db_operation(
        'quiz_admin_results',
        function() {
            global $wpdb;
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}quiz_results ORDER BY score DESC LIMIT %d",
                    20
                )
            );
        }
    );


?>
    <div class="wrap">
        <h1>Joburg Quiz Leaderboard</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Name</th>
                    <th>Score</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($results as $index => $result) {
                    echo "<tr>";
                    echo "<td>" . esc_html($index + 1) . "</td>";
                    echo "<td>" . esc_html($result->first_name . ' ' . $result->last_name) . "</td>";
                    echo "<td>" . esc_html($result->score) . "</td>";
                    echo "<td>" . esc_html($result->completion_time) . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php
}
