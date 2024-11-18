<?php
/*
Plugin Name: WordPress JHB Quiz
Description: An interactive quiz plugin with leaderboard functionality
Version: 1.0
Author: Distinct
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Plugin activation hook
register_activation_hook(__FILE__, 'wp_jhb_quiz_activate');

function wp_jhb_quiz_activate() {
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
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'wp_jhb_quiz_enqueue_scripts');

function wp_jhb_quiz_enqueue_scripts() {
    wp_enqueue_style('wp-jhb-quiz-style', plugins_url('css/quiz-style.css', __FILE__));
    wp_enqueue_script('wp-jhb-quiz-script', plugins_url('js/quiz-script.js', __FILE__), array('jquery'), '1.0', true);
    
    // Pass data to JavaScript
    wp_localize_script('wp-jhb-quiz-script', 'wpQuizGame', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_jhb_quiz_nonce'),
        'questions' => get_quiz_questions()
    ));
}

// Get quiz questions
function get_quiz_questions() {
    return array(
        array(
            'question' => 'What is WordPress primarily used for?',
            'options' => array(
                'Online Shopping',
                'Website Creation',
                'Video Editing',
                'Email Marketing'
            ),
            'correct' => 1
        ),
        array(
            'question' => 'What year was WordPress first released?',
            'options' => array(
                '2001',
                '2003',
                '2005',
                '2007'
            ),
            'correct' => 1
        ),
        array(
            'question' => 'Which of these is NOT a WordPress post type?',
            'options' => array(
                'Blog Post',
                'Page',
                'Email',
                'Media'
            ),
            'correct' => 2
        ),
        array(
            'question' => 'What\'s the name of the latest WordPress editor?',
            'options' => array(
                'Classic Editor',
                'Gutenberg',
                'Visual Composer',
                'Page Builder'
            ),
            'correct' => 1
        ),
        array(
            'question' => 'What is the Yoast SEO plugin primarily used for in WordPress?',
            'options' => array(
                'Managing social media posts',
                'Optimizing content for search engines',
                'Creating backups',
                'Managing user permissions'
            ),
            'correct' => 1
        ),
        array(
            'question' => 'Which SEO element does the Meta Description tag influence?',
            'options' => array(
                'Search engine rankings directly',
                'Website loading speed',
                'Search result click-through rate',
                'Website security'
            ),
            'correct' => 2
        ),
        array(
            'question' => 'What\'s the difference between WordPress.com and WordPress.org?',
            'options' => array(
                'They\'re exactly the same',
                'WordPress.com is hosted, WordPress.org is self-hosted',
                'WordPress.org is newer',
                'WordPress.com is offline only'
            ),
            'correct' => 1
        ),
        array(
            'question' => 'Which of these is free on WordPress?',
            'options' => array(
                'Themes',
                'Plugins',
                'Both themes and plugins',
                'Neither'
            ),
            'correct' => 2
        ),
        array(
            'question' => 'What\'s a WordPress theme?',
            'options' => array(
                'A background color',
                'A design template for your website',
                'A type of post',
                'A payment system'
            ),
            'correct' => 1
        ),
        array(
            'question' => 'What\'s a WordPress plugin?',
            'options' => array(
                'A charging cable',
                'A website backup',
                'Extra functionality for your website',
                'A type of theme'
            ),
            'correct' => 2
        ),
        array(
            'question' => 'Where can you add widgets in WordPress?',
            'options' => array(
                'Only in posts',
                'Only in pages',
                'In sidebars and designated areas',
                'Cannot add widgets'
            ),
            'correct' => 2
        ),
        array(
            'question' => 'What\'s the WordPress dashboard used for?',
            'options' => array(
                'Viewing your website',
                'Managing your website',
                'Sending emails',
                'Creating graphics'
            ),
            'correct' => 1
        )
    );
}

// Add shortcodes for quiz and leaderboard
add_shortcode('wp_jhb_quiz', 'wp_jhb_quiz_shortcode');
add_shortcode('wp_jhb_leaderboard', 'wp_jhb_leaderboard_shortcode');

function wp_jhb_leaderboard_shortcode() {
    global $wpdb;
    $stats_table = $wpdb->prefix . 'quiz_player_stats';
    
    // Get top 20 players by highest score
    $leaderboard = $wpdb->get_results(
        "SELECT 
            first_name,
            last_name,
            highest_score,
            ROUND(average_score, 1) as average_score,
            last_attempt_score,
            total_attempts,
            last_attempt_time
        FROM $stats_table 
        ORDER BY highest_score DESC, average_score DESC 
        LIMIT 20"
    );
    
    ob_start();
    ?>
    <div class="wp-quiz-leaderboard">
        <h2>Quiz Leaderboard</h2>
        <div class="leaderboard-table-container">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <th>Highest Score</th>
                        <th>Average Score</th>
                        <th>Recent Score</th>
                        <th>Total Attempts</th>
                        <th>Last Played</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaderboard as $index => $player): ?>
                        <tr>
                            <td class="rank"><?php echo $index + 1; ?></td>
                            <td class="player-name">
                                <?php echo esc_html($player->first_name . ' ' . $player->last_name); ?>
                            </td>
                            <td class="highest-score">
                                <?php echo number_format($player->highest_score); ?>
                            </td>
                            <td class="average-score">
                                <?php echo number_format($player->average_score, 1); ?>
                            </td>
                            <td class="recent-score">
                                <?php echo number_format($player->last_attempt_score); ?>
                            </td>
                            <td class="attempts">
                                <?php echo number_format($player->total_attempts); ?>
                            </td>
                            <td class="last-played">
                                <?php echo human_time_diff(strtotime($player->last_attempt_time), current_time('timestamp')); ?> ago
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function wp_jhb_quiz_shortcode() {
    ob_start();
    ?>
    <div id="wp-quiz-game-container" class="wp-quiz-container">
        <div id="quiz-start-screen" class="quiz-screen active">
            <h2>WordPress Knowledge Quiz</h2>
            <div class="name-inputs">
                <input type="text" id="first-name" placeholder="First Name" />
                <input type="text" id="last-name" placeholder="Last Name" />
            </div>
            <button id="start-quiz" class="quiz-button">Start Quiz</button>
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
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                </div>
                <div class="mole-row">
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                </div>
                <div class="mole-row">
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                </div>
                <div class="mole-row">
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                    <div class="mole-hole"><div class="mole"></div></div>
                </div>
            </div>
        </div>
        <div id="quiz-end-screen" class="quiz-screen">
            <h2>Game Complete!</h2>
            <div id="final-score"></div>
            <button id="restart-quiz" class="quiz-button">Play Again</button>
            <a href="https://staging.distinct.africa/wpjoburg/leaderboard/">View Leaderboard</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Handle AJAX requests
add_action('wp_ajax_save_quiz_stats', 'wp_jhb_quiz_save_stats');
add_action('wp_ajax_nopriv_save_quiz_stats', 'wp_jhb_quiz_save_stats');

function wp_jhb_quiz_save_stats() {
    check_ajax_referer('wp_jhb_quiz_nonce', 'nonce');

    global $wpdb;
    $results_table = $wpdb->prefix . 'quiz_results';
    $stats_table = $wpdb->prefix . 'quiz_player_stats';

    $player_id = sanitize_text_field($_POST['player_id']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $score = intval($_POST['score']);

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Insert new result
        $wpdb->insert(
            $results_table,
            array(
                'player_id' => $player_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'score' => $score
            ),
            array('%s', '%s', '%s', '%d')
        );

        // Calculate updated statistics
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_attempts,
                MAX(score) as highest_score,
                AVG(score) as average_score
            FROM $results_table 
            WHERE player_id = %s",
            $player_id
        ));

        // Update or insert player statistics
        $wpdb->replace(
            $stats_table,
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

        $wpdb->query('COMMIT');
        wp_send_json_success(array('message' => 'Score saved successfully'));
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(array('message' => 'Error saving score'));
    }
}

// Add admin menu
add_action('admin_menu', 'wp_jhb_quiz_admin_menu');

function wp_jhb_quiz_admin_menu() {
    add_menu_page(
        'WordPress JHB Quiz',
        'JHB Quiz',
        'manage_options',
        'wp-jhb-quiz',
        'wp_jhb_quiz_admin_page',
        'dashicons-games'
    );
}

function wp_jhb_quiz_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_results';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY score DESC LIMIT 20");
    
    ?>
    <div class="wrap">
        <h1>WordPress JHB Quiz Leaderboard</h1>
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
                    echo "<td>" . ($index + 1) . "</td>";
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