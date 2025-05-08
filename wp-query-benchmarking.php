<?php
/**
 * Plugin Name: WP Query Benchmarking
 * Description: Tests performance of WP_Query with orderby rand vs orderby date and title
 * Version: 1.0.0
 * Author: SirLouen <sir.louen@gmail.com>
 */

class WP_Query_Benchmark {
    private $post_count = 10000;
    private $batch_size = 100;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_generate_dummy_posts', [$this, 'ajax_generate_dummy_posts']);
    }

    // Using Jquery to add posts sequentially
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_wp-query-benchmark') return;
        wp_enqueue_script('rq-benchmark', plugin_dir_url(__FILE__) . 'rq-benchmark.js', ['jquery'], null, true);
        wp_localize_script('rq-benchmark', 'RQBenchmark', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('rq_benchmark_nonce')
        ]);
    }

    public function add_admin_page() {
        add_menu_page(
            'WP Query Benchmark',
            'WP Query Benchmark',
            'manage_options',
            'wp-query-benchmark',
            [$this, 'admin_page_html']
        );
    }

    public function admin_page_html() {
        echo '<div class="wrap"><h1>Query Performance Test</h1>';

        echo '
        <div id="rq-progress-wrap">
            <p>Dummy posts: <span id="rq-current-count">' . number_format(wp_count_posts()->publish) . '</span> / <span id="rq-total-count">' . number_format($this->post_count) . '</span></p>
            <div id="rq-progress-bar" style="width: 100%; background: #eee; height: 20px; border-radius: 3px; margin-bottom: 10px;">
                <div id="rq-progress-bar-inner" style="background: #0073aa; width:0%; height:100%; border-radius:3px;"></div>
            </div>
            <button id="rq-generate-btn" class="button button-primary">Generate ' . number_format($this->post_count) . ' Dummy Posts</button>
            <div id="rq-generation-status"></div>
        </div>
        ';

        echo '
        <form method="post">
            <p><input type="submit" name="run_test" class="button" value="Run Performance Test"></p>
        </form>';

        if (isset($_POST['run_test'])) {
            $this->run_performance_test();
        }

        echo '</div>';
    }

    public function ajax_generate_dummy_posts() {
        check_ajax_referer('rq_benchmark_nonce', 'nonce');

        $current_count = intval($_POST['current_count']);
        $to_create = min($this->batch_size, $this->post_count - $current_count);

        if ($to_create <= 0) {
            wp_send_json_success([
                'done' => true,
                'current_count' => $current_count
            ]);
        }

        $created = 0;
        for ($i = 0; $i < $to_create; $i++) {
            $post_id = wp_insert_post([
                'post_title'   => 'Test Post ' . uniqid(),
                'post_content' => 'Dummy content',
                'post_status'  => 'publish',
                'post_type'    => 'post'
            ]);
            if ($post_id) $created++;
        }

        $new_count = $current_count + $created;
        $done = $new_count >= $this->post_count;

        wp_send_json_success([
            'done' => $done,
            'current_count' => $new_count
        ]);
    }

    private function run_performance_test() {
   
        $iterations = 10;
        $posts_per_page = 1000;
        $results = [
            'rand' => ['times' => [], 'queries' => []],
            'date' => ['times' => [], 'queries' => []],
            'title' => ['times' => [], 'queries' => []]
        ];
    
        // 1. Test orderby=rand
        for ($i = 0; $i < $iterations; $i++) {
            $queries_before = get_num_queries();
            $start = microtime(true);
            
            new WP_Query([
                'post_type' => 'post',
                'posts_per_page' => $posts_per_page,
                'orderby' => 'rand',
                'no_found_rows' => true,
                'cache_results' => false
            ]);
            
            $results['rand']['times'][] = microtime(true) - $start;
            $results['rand']['queries'][] = get_num_queries() - $queries_before;
        }
    
        // 2. Test orderby=date
        for ($i = 0; $i < $iterations; $i++) {
            $queries_before = get_num_queries();
            $start = microtime(true);
            
            new WP_Query([
                'post_type' => 'post',
                'posts_per_page' => $posts_per_page,
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
                'cache_results' => false
            ]);
            
            $results['date']['times'][] = microtime(true) - $start;
            $results['date']['queries'][] = get_num_queries() - $queries_before;
        }

        // 3. Test orderby=title
        for ($i = 0; $i < $iterations; $i++) {
            $queries_before = get_num_queries();
            $start = microtime(true);
            
            new WP_Query([
                'post_type' => 'post',
                'posts_per_page' => $posts_per_page,
                'orderby' => 'title',
                'order' => 'DESC',
                'no_found_rows' => true,
                'cache_results' => false
            ]);
            
            $results['title']['times'][] = microtime(true) - $start;
            $results['title']['queries'][] = get_num_queries() - $queries_before;
        }
    
        echo '<h3>Performance Results (' . $posts_per_page .' posts per query, ' . $iterations . ' iterations)</h3>';
        echo '<table class="widefat">
            <thead>
                <tr>
                    <th>Order By</th>
                    <th>Avg Time</th>
                    <th>Avg Queries</th>
                    <th>Min Time</th>
                    <th>Max Time</th>
                    <th>Min Queries</th>
                    <th>Max Queries</th>
                </tr>
            </thead>
            <tbody>';
    
        foreach ($results as $orderby => $data) {
            echo '<tr>
                <td>' . esc_html($orderby) . '</td>
                <td>' . number_format(array_sum($data['times']) / $iterations, 4) . 's</td>
                <td>' . number_format(array_sum($data['queries']) / $iterations, 1) . '</td>
                <td>' . number_format(min($data['times']), 4) . 's</td>
                <td>' . number_format(max($data['times']), 4) . 's</td>
                <td>' . min($data['queries']) . '</td>
                <td>' . max($data['queries']) . '</td>
            </tr>';
        }
        
        echo '</tbody></table>';
    }    
}

new WP_Query_Benchmark();