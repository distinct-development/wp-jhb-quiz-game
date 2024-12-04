<?php

// Register Custom Post Type for Quiz Questions
add_action('init', 'distinct_jhb_quiz_register_question_cpt');
function distinct_jhb_quiz_register_question_cpt() {
    $labels = array(
        'name'                  => 'Quiz Questions',
        'singular_name'         => 'Quiz Question',
        'menu_name'            => 'Quiz Questions',
        'add_new'              => 'Add New Question',
        'add_new_item'         => 'Add New Quiz Question',
        'edit_item'            => 'Edit Quiz Question',
        'new_item'             => 'New Quiz Question',
        'view_item'            => 'View Quiz Question',
        'search_items'         => 'Search Quiz Questions',
        'not_found'            => 'No quiz questions found',
        'not_found_in_trash'   => 'No quiz questions found in trash'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'distinct-jhb-quiz',
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'supports'            => array('title', 'editor'),
        'menu_position'       => null,
        'has_archive'         => false,
        'show_in_rest'        => true
    );

    register_post_type('quiz_question', $args);
}

// Add meta box for question options and correct answer
add_action('add_meta_boxes', 'distinct_jhb_quiz_add_meta_boxes');
function distinct_jhb_quiz_add_meta_boxes() {
    add_meta_box(
        'quiz_question_options',
        'Question Options',
        'distinct_jhb_quiz_question_options_callback',
        'quiz_question',
        'normal',
        'high'
    );
}

// Meta box callback function
function distinct_jhb_quiz_question_options_callback($post) {
    // Generate and verify nonce
    wp_nonce_field('distinct_jhb_quiz_save_meta', 'distinct_jhb_quiz_meta_nonce');

    // Get existing options if any
    $options = get_post_meta($post->ID, '_quiz_question_options', true);
    $correct_answer = get_post_meta($post->ID, '_quiz_correct_answer', true);

    if (!is_array($options)) {
        $options = array('', '', '', ''); // Default to 4 empty options
    }
    ?>
    <div class="quiz-options-container">
        <p><strong>Enter the options for this question:</strong></p>
        <?php foreach ($options as $index => $option): ?>
            <div class="option-row" style="margin-bottom: 10px;">
                <label>
                    <input type="radio" name="quiz_correct_answer" value="<?php echo esc_attr($index); ?>"
                           <?php checked($correct_answer, $index); ?>>
                    <?php 
                        /* translators: %d: option number */
                        echo sprintf(
                            esc_html__('Option %d:', 'distinct-jhb-quiz'),
                            esc_html($index + 1)
                        ); 
                    ?>
                </label>
                <input type="text" name="quiz_options[]" value="<?php echo esc_attr($option); ?>"
                       style="width: 80%;" required>
            </div>
        <?php endforeach; ?>
        <p class="description">Select the radio button next to the correct answer.</p>
    </div>
    <?php
}

// Save meta box data
add_action('save_post_quiz_question', 'distinct_jhb_quiz_save_meta', 10, 2);
function distinct_jhb_quiz_save_meta($post_id, $post) {
    // Security checks
    if (!isset($_POST['distinct_jhb_quiz_meta_nonce'])) {
        return;
    }

    // Unslash and sanitize nonce before verification
    $nonce = sanitize_text_field(wp_unslash($_POST['distinct_jhb_quiz_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'distinct_jhb_quiz_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save options with proper unslashing and sanitization
    if (isset($_POST['quiz_options']) && is_array($_POST['quiz_options'])) {
        $raw_options = wp_unslash($_POST['quiz_options']); // First unslash the entire array
        $options = array_map('sanitize_text_field', $raw_options); // Then sanitize each element
        update_post_meta($post_id, '_quiz_question_options', $options);
    }

    // Save correct answer with proper unslashing and sanitization
    if (isset($_POST['quiz_correct_answer'])) {
        $correct_answer = absint(wp_unslash($_POST['quiz_correct_answer']));
        update_post_meta($post_id, '_quiz_correct_answer', $correct_answer);
    }
}

// Replace the hardcoded get_quiz_questions function with one that fetches from CPT
function get_quiz_questions() {
    // Cache key based on the last updated quiz question
    $cache_key = 'distinct_jhb_quiz_questions_' . wp_cache_get_last_changed('posts');
    $questions = wp_cache_get($cache_key, 'distinct_jhb_quiz');

    if (false === $questions) {
        $args = array(
            'post_type' => 'quiz_question',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'rand'
        );

        $query = new WP_Query($args);
        $questions = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $options = get_post_meta(get_the_ID(), '_quiz_question_options', true);
                $correct_answer = get_post_meta(get_the_ID(), '_quiz_correct_answer', true);

                if (!empty($options) && is_array($options)) {
                    $questions[] = array(
                        'question' => get_the_title(),
                        'options' => $options,
                        'correct' => intval($correct_answer)
                    );
                }
            }
            wp_reset_postdata();
        }

        wp_cache_set($cache_key, $questions, 'distinct_jhb_quiz', HOUR_IN_SECONDS);
    }

    return $questions;
}

// Add custom columns to questions list
add_filter('manage_quiz_question_posts_columns', 'distinct_jhb_quiz_question_columns');
function distinct_jhb_quiz_question_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        if ($key == 'date') {
            $new_columns['options'] = 'Options';
            $new_columns['correct_answer'] = 'Correct Answer';
        }
        $new_columns[$key] = $value;
    }
    return $new_columns;
}

// Fill custom columns with data
add_action('manage_quiz_question_posts_custom_column', 'distinct_jhb_quiz_question_column_content', 10, 2);
function distinct_jhb_quiz_question_column_content($column, $post_id) {
    switch ($column) {
        case 'options':
            $options = get_post_meta($post_id, '_quiz_question_options', true);
            if (is_array($options)) {
                echo implode(', ', array_map('esc_html', $options));
            }
            break;
        case 'correct_answer':
            $correct = get_post_meta($post_id, '_quiz_correct_answer', true);
            $options = get_post_meta($post_id, '_quiz_question_options', true);
            if (isset($options[$correct])) {
                echo esc_html($options[$correct]);
            }
            break;
    }
}

// Add bulk actions for questions
add_filter('bulk_actions-edit-quiz_question', 'distinct_jhb_quiz_register_bulk_actions');
function distinct_jhb_quiz_register_bulk_actions($bulk_actions) {
    $bulk_actions['duplicate_questions'] = __('Duplicate', 'distinct-jhb-quiz');
    return $bulk_actions;
}

// Handle bulk actions with nonce verification
add_filter('handle_bulk_actions-edit-quiz_question', 'distinct_jhb_quiz_handle_bulk_actions', 10, 3);
function distinct_jhb_quiz_handle_bulk_actions($redirect_to, $action, $post_ids) {
    // Verify nonce
    if (!isset($_REQUEST['_wpnonce'])) {
        wp_die('Security check failed');
    }
    
    $nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
    if (!wp_verify_nonce($nonce, 'bulk-posts')) {
        wp_die('Security check failed');
    }

    if ($action !== 'duplicate_questions') {
        return $redirect_to;
    }

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        $options = get_post_meta($post_id, '_quiz_question_options', true);
        $correct_answer = get_post_meta($post_id, '_quiz_correct_answer', true);

        // Create duplicate
        $new_post_id = wp_insert_post(array(
            'post_title' => $post->post_title . ' (Copy)',
            'post_content' => $post->post_content,
            'post_type' => 'quiz_question',
            'post_status' => 'draft'
        ));

        if ($new_post_id) {
            update_post_meta($new_post_id, '_quiz_question_options', $options);
            update_post_meta($new_post_id, '_quiz_correct_answer', $correct_answer);
        }
    }

    $redirect_to = add_query_arg('bulk_duplicated', count($post_ids), $redirect_to);
    return $redirect_to;
}

// Show admin notice after bulk action
add_action('admin_notices', 'distinct_jhb_quiz_bulk_action_admin_notice');
function distinct_jhb_quiz_bulk_action_admin_notice() {
    if (!isset($_REQUEST['bulk_duplicated'])) {
        return;
    }

    // Verify nonce for the page
    if (!isset($_REQUEST['_wpnonce'])) {
        return;
    }
    
    $nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
    if (!wp_verify_nonce($nonce, 'bulk-posts')) {
        return;
    }

    $count = intval(wp_unslash($_REQUEST['bulk_duplicated']));
    /* translators: %d: number of questions duplicated */
    $message = sprintf(
        esc_html(_n(
            '%d question duplicated successfully.',
            '%d questions duplicated successfully.',
            $count,
            'distinct-jhb-quiz'
        )),
        esc_html($count)
    );
    printf(
        '<div class="updated"><p>%s</p></div>',
        wp_kses_post($message)
    );
}