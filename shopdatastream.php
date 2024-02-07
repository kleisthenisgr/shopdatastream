<?php
    /**
     * Plugin Name: WooCommerce Sales & Cart Logger
     * Description: Logs sales and cart data to a file.
     * Version: 2.0.0
     * Author: Alexios-Theocharis Koilias
     * Author URI: https://enterthe.shop/
     */
     
    // Exit if accessed directly.
    if (!defined('ABSPATH')) {
        exit;
    }
    function get_country_from_ip($ip)
    {
        // Check if the country info is already stored in session
        if (isset($_SESSION['user_country'])) {
            return $_SESSION['user_country'];
        }
        $api_key = 'e748abef2c34430f2ed31e1d2379fe02';
        $api_url = "https://www.iplocate.io/api/lookup/" . $ip . "/json";
     
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "X-API-Key: {$api_key}",
        ));
     
        $response = curl_exec($ch);
        curl_close($ch);
     
        if ($response === false) {
            // Handle cURL error
            error_log('IP geolocation API error: ' . curl_error($ch));
            return 'Unknown';
        }
     
        $res = json_decode($response, true);
     
        if (isset($res['country'])) {
            // Store the country info in the session for future use
            $_SESSION['user_country'] = $res['country'];
            return $res['country'];
        } else {
            return 'Unknown';
        }
    }
     
     
     
    function generate_unique_session_id() {
        if (!session_id()) {
            session_start();
        }
     
        if (!isset($_SESSION['user_session_id'])) {
            $_SESSION['user_session_id'] = uniqid();
        }
    }
    add_action('init', 'generate_unique_session_id');
     
    function day_translate()
    {	
    	date_default_timezone_set("Europe/Athens");
    	if (date("l")== "Friday")
    	{
    		$day = "Παρασκευή";
    	}
    	elseif (date("l")== "Saturday")
    	{
    		$day = "Σάββατο";
    	}
    	elseif (date("l")== "Sunday")
    	{
    		$day = "Κυριακή";
    	}
    	elseif (date("l")== "Monday")
    	{
    		$day = "Δευτέρα";
    	}
    	elseif (date("l")== "Tuesday")
    	{
    		$day = "Τρίτη";
    	}
    	elseif (date("l")== "Wednesday")
    	{
    		$day = "Τετάρτη";
    	}
    	else
    	{
    		$day = "Πέμπτη";
    	}
    	return $day;
    }
     
    add_action('woocommerce_thankyou', 'log_order_after_submission', 10, 1);
    function log_order_after_submission($order_id) {
        $order = wc_get_order($order_id);
     
        date_default_timezone_set("Europe/Athens");
     
        // Get an array of product IDs for the order
        $product_ids_array = array();
        foreach ($order->get_items() as $item_key => $item) {
            $product_ids_array[] = $item->get_product_id();
        }
     
        // Convert the array of product IDs to a comma-separated string
        $product_ids_string = implode(',', $product_ids_array);
     
        $order_data = array(
            'order_id' => $order_id,
            'order_total' => $order->get_total(),
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'day' => day_translate(),
            'session_id' => isset($_SESSION['user_session_id']) ? $_SESSION['user_session_id'] : uniqid(),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'country' => get_country_from_ip($_SERVER['REMOTE_ADDR']),
            'product_ids' => $product_ids_string, // Add the product IDs to the order data
            // Add more relevant order data as needed
        );
     
        // Log to a file (replace 'sales_log.txt' with your desired file path)
        file_put_contents('sales_log.txt', print_r($order_data, true), FILE_APPEND);
     
        $remote_db_host = '89.116.147.52';
        $remote_db_user = 'u448459142_remote';
        $remote_db_pass = '7258647@Bc';
        $remote_db_name = 'u448459142_remote';
     
        $remote_db_connection = new mysqli($remote_db_host, $remote_db_user, $remote_db_pass, $remote_db_name);
        if ($remote_db_connection->connect_error) {
            // Handle connection error
            return;
        }
     
        $stmt = $remote_db_connection->prepare("INSERT INTO order_data (order_id, order_total, order_date, day, session_id, ip_address, country, product_ids) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo 'Error preparing the statement: ' . $remote_db_connection->error;
            return;
        }
     
        $stmt->bind_param("idssssss", $order_data['order_id'], $order_data['order_total'], $order_data['order_date'], $order_data['day'], $order_data['session_id'], $order_data['ip_address'], $order_data['country'], $order_data['product_ids']);
        $stmt->execute();
     
        $stmt->close();
        $remote_db_connection->close();
    }
     
     
    // Hook to log cart data
    add_action('woocommerce_after_cart', 'log_cart_data');
    function log_cart_data() {
        if (is_cart()) {
            $cart_data = array();
    		date_default_timezone_set("Europe/Athens");
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];
                $cart_data[] = array(
                    'product_id' => $product->get_id(),
                    'product_name' => $product->get_name(),
                    'quantity' => $cart_item['quantity'],
                    'subtotal' => $cart_item['line_subtotal'],
    				'date_time' => date('Y-m-d H:i:s'),
    				'day' => day_translate(),
    				'session_id' => isset($_SESSION['user_session_id']) ? $_SESSION['user_session_id'] : uniqid(),
    				'ip_address' => $_SERVER['REMOTE_ADDR'],
    				'country' => get_country_from_ip($_SERVER['REMOTE_ADDR']),
                );
            }
     
            // Log to a file (replace 'cart_log.txt' with your desired file path)
            file_put_contents('cart_log.txt', print_r($cart_data, true), FILE_APPEND);
    		
    		$remote_db_host = '89.116.147.52';
            $remote_db_user = 'u448459142_remote';
            $remote_db_pass = '7258647@Bc';
            $remote_db_name = 'u448459142_remote';
    		
    		$remote_db_connection = new mysqli($remote_db_host, $remote_db_user, $remote_db_pass, $remote_db_name);
    		if ($remote_db_connection->connect_error) {
    			// Handle connection error
    			return;
    		}
     
    		$stmt = $remote_db_connection->prepare("INSERT INTO cart_data (product_id, product_name, quantity, subtotal, date_time, day, session_id, ip_address, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    		if (!$stmt) {
    			echo 'Error preparing the statement: ' . $remote_db_connection->error;
    			return;
    		}
     
    		foreach ($cart_data as $item) {
    			$stmt->bind_param("isdssssss", $item['product_id'], $item['product_name'], $item['quantity'], $item['subtotal'], $item['date_time'], $item['day'], $item['session_id'], $item['ip_address'], $item['country']);
    			$stmt->execute();
    		}
     
    		$stmt->close();
    		$remote_db_connection->close();
     
    			}
    }
     
    add_action('woocommerce_before_single_product', 'log_product_view');
    function log_product_view() {
        if (function_exists('is_product') && is_product()) {
            global $product;
     
            if (!is_a($product, 'WC_Product')) {
                echo 'log product view exit';
                return; // Exit if $product is not a valid WC_Product object
            }
            date_default_timezone_set("Europe/Athens");
            $product_id = $product->get_id();
            $product_name = $product->get_name();
            $product_price = $product->get_price();
            $view_data = array(
                'product_id' => $product_id,
                'product_name' => $product_name,
                'date_time' => date('Y-m-d H:i:s'),
                'day' => day_translate(),
                'price' => $product_price,
                'session_id' => isset($_SESSION['user_session_id']) ? $_SESSION['user_session_id'] : uniqid(),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'country' => get_country_from_ip($_SERVER['REMOTE_ADDR']),
            );
            // Log to a file (replace 'product_view_log.txt' with your desired file path)
            file_put_contents('product_view_log.txt', print_r($view_data, true), FILE_APPEND);
     
            $remote_db_host = '89.116.147.52';
            $remote_db_user = 'u448459142_remote';
            $remote_db_pass = '7258647@Bc';
            $remote_db_name = 'u448459142_remote';
     
            // Connect to the remote database
            $remote_db_connection = new mysqli($remote_db_host, $remote_db_user, $remote_db_pass, $remote_db_name);
     
            // Check if the connection was successful
            if ($remote_db_connection->connect_error) {
                // Handle connection error
                return;
            }
     
            // Check if a row with the same product_id and session_id exists
            $check_stmt = $remote_db_connection->prepare("SELECT id FROM remote_product_views WHERE product_id = ? AND session_id = ? LIMIT 1");
            if ($check_stmt) {
                $check_stmt->bind_param("is", $product_id, $view_data['session_id']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
     
                // If a row exists, update the date_time value
                if ($check_result->num_rows > 0) {
                    $update_stmt = $remote_db_connection->prepare("UPDATE remote_product_views SET date_time = ? WHERE product_id = ? AND session_id = ? LIMIT 1");
                    if ($update_stmt) {
                        $update_stmt->bind_param("sis", $view_data['date_time'], $product_id, $view_data['session_id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                } else {
                    // If no row exists, insert a new row
                    $insert_stmt = $remote_db_connection->prepare("INSERT INTO remote_product_views (product_id, product_name, date_time, day, session_id, ip_address, country, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($insert_stmt) {
                        $insert_stmt->bind_param("isssssss", $product_id, $product_name, $view_data['date_time'], $view_data['day'], $view_data['session_id'], $view_data['ip_address'], $view_data['country'], $view_data['price']);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                }
     
                $check_stmt->close();
            }
     
            $remote_db_connection->close();
        }
    }
     
     
    function check_add_to_cart_url() {
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'add-to-cart') !== false) {
            echo 'AAAAAAAAAAOOOOOO';
            $view_data = array(
                'order_id' => 555,
                'order_total' => 'superwow'
            );
            file_put_contents('before_cart_data.txt', print_r($view_data, true), FILE_APPEND);
        }
    }
     
    // Hook the function to the parse_request action
    add_action('parse_request', 'check_add_to_cart_url');
     
    // Enqueue custom JavaScript inline script
    function my_plugin_enqueue_scripts() {
        echo '<script>';
        echo 'jQuery(document).ready(function($) {';
        echo '$(document).on("click", ".add_to_cart_button", function() {';
        echo 'alert("AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA");';
        echo '});';
        echo '});';
        echo '</script>';
    }
    add_action('wp_footer', 'my_plugin_enqueue_scripts');
     
     
     
    /* Country lookup by ip API key: e748abef2c34430f2ed31e1d2379fe02 */

Close
