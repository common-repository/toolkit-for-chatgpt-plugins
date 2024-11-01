<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('DCS_ChatGPT_WP_Plugin_REST_Endpoints')) {
    class DCS_ChatGPT_WP_Plugin_REST_Endpoints {

        public function __construct() {

            add_action('rest_api_init', array($this, 'register_wordpress_rest_endpoints'));

            if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
                add_action('rest_api_init', array($this, 'register_woocommerce_rest_endpoints'));
            }
        }

        public function register_wordpress_rest_endpoints() {
            // Accessed via /wp-json/chatgpt-plugin/v1/posts/search?term=
            register_rest_route('chatgpt-plugin/v1', '/posts/search', array(
                'methods' => 'GET',
                'callback' => array($this, 'wordpress_post_search'),
                'args' => array(
                    'term' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    )
                )
            ));
        }

        public function register_woocommerce_rest_endpoints() {
            // Accessed via /wp-json/chatgpt-plugin/v1/products/search?term=
            register_rest_route('chatgpt-plugin/v1', '/products/search', array(
                'methods' => 'GET',
                'callback' => array($this, 'woocommerce_product_search'),
                'args' => array(
                    'term' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    )
                )
            ));
        }
        
        public function woocommerce_product_search(WP_REST_Request $request) {

            $search_term = $request->get_param( 'term' );
            $response = array();
        
            // Validate and sanitize input
            $search_term = sanitize_text_field( $search_term );
            
            // Limit search to specific post type
            $args = array(
            'post_type' => 'product',
            's' => $search_term
            );
            
            // Query posts
            $query = new WP_Query( $args );
        
            // Build response
            if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $response[] = array(
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'permalink' => get_permalink()
                );
            }
            }

            wp_reset_postdata();
        
            // Return response
            if ( empty( $response ) ) {
            return new WP_Error( 'no_results', 'No results found.', array( 'status' => 404 ) );
            } else {
                return new WP_REST_Response($response, 200);
            return $response;
            }

        }

        public function wordpress_post_search(WP_REST_Request $request) {

            $search_term = $request->get_param( 'term' );
            $response = array();
        
            // Validate and sanitize input
            $search_term = sanitize_text_field( $search_term );
            
            // Limit search to specific post type
            $args = array(
                'post_type' => 'post',
                's' => $search_term
            );
            
            // Query posts
            $query = new WP_Query( $args );
            
            // Build response
            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $response[] = array(
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'permalink' => get_permalink()
                    );
                }
            }
        
            wp_reset_postdata();
            
            // Return response
            if ( empty( $response ) ) {
                return new WP_Error( 'no_results', 'No results found.', array( 'status' => 404 ) );
            } else {
                return new WP_REST_Response($response, 200);
            }
        
        }
        
    }

    $endpoints = new DCS_ChatGPT_WP_Plugin_REST_Endpoints();
}