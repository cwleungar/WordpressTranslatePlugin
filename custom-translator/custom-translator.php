<?php
/*
Plugin Name: Custom Translator
Description: A simple custom page plugin that proxies /en/*, /zhHK/*, and /jp/* requests.
Version: 1.0
Author: Sam Leung (github: cwleungar)
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

    // Retrieve cookies from the client
    $cookies = array_map(function($name) {
        return "$name=" . urlencode($_COOKIE[$name]);
    }, array_keys($_COOKIE));

    // Prepare the headers including cookies
    $headers = array(
        'Cookie' => implode('; ', $cookies),
    );

    while ($redirect_count < $max_redirects) {
        // Fetch the content from the target URL without following redirects, including cookies
        $response = wp_remote_get($target_url, array(
            'redirection' => 0,
            'headers' => $headers,
        ));

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
                //echo '<p>Redirecting to: ' . esc_html($redirect_url) . '</p>';
                // Update the target URL for the next iteration
                $target_url = $redirect_url;
                $redirect_count++; // Increment the redirect counter
                continue; // Fetch the new URL
            }
        }

        // If no redirect, display the content
        $body = wp_remote_retrieve_body($response);
        break; // Exit the loop if no redirects
    }

    if ($target_url_o !== $target_url) {
        // Extract the new path from the redirect URL
        $redirect_parts = parse_url($target_url);
        $new_path = $redirect_parts['path'];                
        // Construct the new URL with the language code
        $new_redirect_url = "/$lang_code$new_path";

        // Avoid redirecting to the same URL
        if ($new_redirect_url !== $_SERVER['REQUEST_URI']) {
            echo '<p>Final redirect to: ' . esc_html($new_redirect_url) . '</p>';
            wp_redirect($new_redirect_url, 301);
            exit;
        }
    }

    echo $body;

    // Return the buffered content
    return ob_get_clean();
}

// Proxy specified language requests to the custom page
function custom_translator_proxy() {
    // Define the allowed language codes
    $lang_codes = ['zh', 'jp'];

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
        'zh' => '繁體中文',
        'jp' => '日本語'
    ];

    global $wp; // Access the global $wp variable

    // Start the switcher output
    $output = '<div class="language-switcher">
                   <button class="dropdown-toggle">Select Language</button>
                   <div class="dropdown-menu">';

    // Get the current URL without query args
    $current_url = home_url(add_query_arg([], $wp->request));
    
    // Determine the current language code from the URL
    $current_lang_code = '';
    foreach ($languages as $code => $name) {
        if (strpos($current_url, '/' . $code . '/') !== false) {
            $current_lang_code = $code;
            break;
        }
    }

    // Get the path after the domain
    $path = parse_url($current_url, PHP_URL_PATH);
    
    foreach ($languages as $code => $name) {
        if ($code === 'en') {
            // For English, link to the base path without language code
            $lang_url = preg_replace('/\/(zh|jp)(\/|$)/', '', $current_url);
        } else {
            // For other languages, prepend the language code to the path
            if ($current_lang_code === $code) {
                // Skip the current language
                continue;
            }
            $lang_url = home_url('/' . $code . $path);
        }

        // Create the switcher links
        $output .= '<a href="' . esc_url(rtrim($lang_url, '/') . '/') . '">' . esc_html($name) . '</a>';
    }

    $output .= '</div></div>';

    return $output;
}

// Register the shortcode
add_shortcode('language_switcher', 'custom_language_switcher');


add_action('admin_menu', 'my_plugin_menu');

function my_plugin_menu() {
    add_menu_page(
        'Translation Settings',
        'Translation Settings',
        'manage_options',
        'translation-settings',
        'my_plugin_translation_settings_page',
        'dashicons-translation'
    );

    // Add submenu pages for each JSON file
    $files = glob(plugin_dir_path(__FILE__) . 'translation_file/*.json');
    foreach ($files as $file) {
        $filename = basename($file);
        add_submenu_page(
            'translation-settings',
            ucfirst(str_replace('.json', '', $filename)),
            ucfirst(str_replace('.json', '', $filename)),
            'edit_posts',
            'translation-settings-' . $filename,
            function() use ($file) {
                my_plugin_translation_file_page($file);
            }
        );
    }
}

function my_plugin_translation_file_page($file_path) {
    // Read the JSON file
    $json_data = file_get_contents($file_path);
    $data = json_decode($json_data, true) ?: [];

    // Handle form submission
   if ($_SERVER['REQUEST_METHOD'] == 'POST') {
$data = $_POST['data'] ?? [];
$value_data = $_POST['value'] ?? [];


		// Prepare the data for saving
		$new_data = [];
		foreach ($data as $key => $value) {
    if (!empty($key)) {
        // Replace characters in the value

        $value_data[$key] = str_replace(
            ['\\n','\\\'', "\\\"",'"', "'",'\\\\' ],
            [ '\n','‘', '“','“', '‘','\\'],
            $value_data[$key] ?? ''
        );
		$value=str_replace(
            ['\\n','\\\'', "\\\"",'"', "'",'\\\\' ],
            [ '\n','‘', '“','“', '‘','\\'],
            $value ?? ''
        );


        $script_value = json_encode($value_data[$key]);
        echo "<script>console.log($script_value);</script>";
        // Link keys to their values
        $new_data[$value] = $value_data[$key] ?? '';
    }
}
	

		$json_data = json_encode($new_data);

		
		// Add console log for checking the data


				// Write updated data back to the JSON file
		if (file_put_contents($file_path, json_encode($new_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))=== false) {
			echo '<div class="error"><p>Error saving settings. Please check file permissions.</p></div>';
		} else {
			echo '<script>alert("Settings saved successfully."); setTimeout(function() { window.location.reload(); }, 500);</script>';
		}
		
	}

    // Sort the data by key
    ksort($data);

    // Display the form
    ?>
    <h2><?php echo esc_html(ucfirst(basename($file_path, '.json'))); ?></h2>
    <input type="text" id="search" placeholder="Search..." />
    <form method="post" id="translation-form">
        <table class="form-table" id="translation-table">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $key => $value): ?>
                <tr>
                    <td><input readonly type="text" name="data[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($key); ?>" /></td>
                    <td><input type="text" name="value[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" /></td>
                    <td><button type="button" class="remove-row button">Remove</button></td>
                </tr>
                <?php endforeach; ?>

                <!-- Placeholder for new rows will be added dynamically -->
            </tbody>
        </table>
        <button type="button" id="add-row" class="button">Add Key-Value Pair</button>
        <input type="submit" value="Save Changes" class="button button-primary" />
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add new row
        document.getElementById('add-row').addEventListener('click', function() {
            const tableBody = document.querySelector('#translation-table tbody');
            const newRow = document.createElement('tr');
            // Use unique identifiers for new key-value pairs
            newRow.innerHTML = `
                <td><input type="text" name="data[new_key_${Date.now()}]" placeholder="New Key" /></td>
                <td><input type="text" name="value[new_key_${Date.now()}]" placeholder="New Value" /></td>
                <td><button type="button" class="remove-row button">Remove</button></td>
            `;
            tableBody.appendChild(newRow);
        });

        // Remove row
        document.querySelector('#translation-table').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-row')) {
                e.target.closest('tr').remove();
            }
        });

        // Search functionality
        document.getElementById('search').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('#translation-table tbody tr');
            rows.forEach(row => {
                const keyCell = row.querySelector('td input[type="text"]').value.toLowerCase();
                row.style.display = keyCell.includes(query) ? '' : 'none';
            });
        });
    });
    </script>
    <?php
}