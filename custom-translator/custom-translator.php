<?php
/*
Plugin Name: Custom Translator
Description: A simple custom page plugin that proxies /en/*, /zhHK/*, and /jp/* requests.
Version: 1.0
Author: Your Name
*/

function custom_translator_page() {
    // Start output buffering
    ob_start();

    // Get the requested URI
    $request_uri = $_SERVER['REQUEST_URI'];

    // Extract the language code and path
    $parts = explode('/', trim($request_uri, '/'));
    $lang_code = $parts[0]; // First part is the language code
    $path = implode('/', array_slice($parts, 1)); // Join the remaining parts as the path

    $server_host = $_SERVER['HTTP_HOST']; // Get the server host
    $target_url = "https://$server_host/$path"; 
	$target_url_o = $target_url; 
    // Initialize redirect tracking
    $max_redirects = 10; // Set a maximum number of redirects
    $redirect_count = 0; // Initialize redirect counter

    while ($redirect_count < $max_redirects) {
        // Fetch the content from the target URL without following redirects
        $response = wp_remote_get($target_url, array('redirection' => 0));

        // Check for errors
        if (is_wp_error($response)) {
            echo '<p>Error fetching content: ' . esc_html($response->get_error_message()) . '</p>';
            break; // Exit the loop on error
        }

        $status_code = wp_remote_retrieve_response_code($response);

        // Track if a redirect occurred
        if ($status_code === 301 || $status_code === 302) {
            $redirect_url = wp_remote_retrieve_header($response, 'Location');
            if ($redirect_url) {
                // Log or display the redirect
                echo '<p>Redirecting to: ' . esc_html($redirect_url) . '</p>';
                
                // Update the target URL for the next iteration
                $target_url = $redirect_url;
                $redirect_count++; // Increment the redirect counter
                continue; // Fetch the new URL
            }
        }
		

        // If no redirect, display the content
        $body = wp_remote_retrieve_body($response);
         // Display the content from the target URL
        break; // Exit the loop if no redirects
    }

 	if ($target_url_o!=$target_url) {
                 // Extract the new path from the redirect URL
                 $redirect_parts = parse_url($target_url);
                 $new_path = $redirect_parts['path'];				
                 // Construct the new URL with the language code
                 $new_redirect_url = "/$lang_code$new_path";
 				echo '<p>final to: ' . esc_html($new_redirect_url) . '</p>';
                 // Send a 301 redirect to the new URL
                 wp_redirect($new_redirect_url, 301);
 				exit;
 		}
	echo $body;
    // Get the server's IP address

    ?>
    <?php

    // Return the buffered content
    return ob_get_clean();
}

// Proxy specified language requests to the custom page
function custom_translator_proxy() {
    // Define the allowed language codes
    $lang_codes = ['en', 'zhHK', 'jp'];

    // Create a regex pattern to match the language codes
    $pattern = '/^\/(' . implode('|', $lang_codes) . ')\/(.*)$/';

    if (preg_match($pattern, $_SERVER['REQUEST_URI'])) {
        // Set the current post ID to a new WP_Query
        global $wp_query;
        $wp_query->is_404 = false; // Prevent 404 error
        status_header(200);
        // Serve the content
        echo custom_translator_page();
        exit;
    }
}

// Hook into the template_redirect action with the highest priority
add_action('template_redirect', 'custom_translator_proxy', 1);
function custom_translator_enqueue_scripts() {

    // Enqueue your translation.js file
    wp_enqueue_script('custom-translator-js', plugin_dir_url(__FILE__) . 'js/translation.js', array(), '1.0', true);
	wp_enqueue_style('custom-translator-css', plugin_dir_url(__FILE__) . 'css/translate.css', array(), '1.0');

}
add_action('wp_enqueue_scripts', 'custom_translator_enqueue_scripts');


function custom_language_switcher() {
    // Define available languages
    $languages = [
        'en' => 'English',
        'zhHK' => '繁體中文',
        'jp' => '日本語'
    ];

    global $wp; // Ensure we have access to the global $wp variable

    // Start the switcher output
    $output = '<div class="language-switcher">
                   <button class="dropdown-toggle">Select Language</button>
                   <div class="dropdown-menu">';

    foreach ($languages as $code => $name) {
        // Create the switcher links
        $current_url = home_url(add_query_arg([], $wp->request)); // Get the current URL without query args
        $lang_url = str_replace('/' . $code . '/', '/', $current_url); // Remove the current language code
        $output .= '<a href="' . esc_url($lang_url . '/' . $code) . '">' . esc_html($name) . '</a>';
    }

    $output .= '</div></div>';

    return $output;
}

// Register the shortcode
add_shortcode('language_switcher', 'custom_language_switcher');