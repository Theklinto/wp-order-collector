<?php

/**
* @package wp-order
*/

/*
Plugin Name: WP Order Collector
Description: Plugin that collects orders from multiple webshops and shows in a single place. With the use of WooCommerce API v3
Version: 1.0
Author: Marius Klint Hansen
Text Domain: wp-order-collector
*/
require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;

//Defining global variables used.
global $wp_order_collector_client;
global $wp_order_collector_master_client;
global $wp_order_collector_table;
global $wpdb;
$wp_order_collector_table = "wp_order_collector";

//Hooks
register_activation_hook( __FILE__, 'wp_order_collector_activation_function' );
register_uninstall_hook( __FILE__, 'wp_order_collector_uninstall_function' );

//Admin menu
add_action("admin_menu", "wp_order_collector_admin_menu", 99);
function wp_order_collector_admin_menu()
{
    $menu_slug = "wp-order-collector";
    $hidden_menu_slug = "wp-order-collector-hidden";
	add_menu_page( "WP Order Collector", "WP Order Collector", "administrator", "$menu_slug", "wp_order_collector_controlpanel");
    add_submenu_page( "$menu_slug", "API keys", "API keys", "administrator", "wp-order-collector-api-keys", "wp_order_collector_controlpanel_api_keys");
    add_submenu_page( "$hidden_menu_slug",//parent page slug
        'API key edit',//page_title
        'API key edit',//menu_title
        'manage_options',//capability
        'wp-order-collector-controlpanel-api-keys-edit',//menu_slug,
        'wp_order_collector_controlpanel_api_keys_edit'// $function
    );
    add_submenu_page( "$hidden_menu_slug",//parent page slug
        'API key add',//page_title
        'API key add',//menu_title
        'manage_options',//capability
        'wp-order-collector-controlpanel-api-keys-add',//menu_slug,
        'wp_order_collector_controlpanel_api_keys_add'// $function
    );
    add_submenu_page( "$hidden_menu_slug",//parent page slug
        'API key delete',//page_title
        'API key delete',//menu_title
        'manage_options',//capability
        'wp-order-collector-controlpanel-api-keys-delete',//menu_slug,
        'wp_order_collector_controlpanel_api_keys_delete'// $function
    );
}

function wp_order_collector_controlpanel()
{

}

function wp_order_collector_controlpanel_api_keys()
{
    custom_log("test");
    global $wpdb;
    global $wp_order_collector_table;
	//require_once('html/wp-order-collector-settings.html');
    $sql = "SELECT * FROM $wp_order_collector_table";
    $html_as_string = '';
    $add_api_section = '';
    $add_link = admin_url('admin.php?page=wp-order-collector-controlpanel-api-keys-add');

    $result = $wpdb->get_results($sql, ARRAY_A);
    if(!(count($result) > 0))
    {
        $html_as_string = "No api keys were found, try adding one :)";
    }
    else
    {
        for($x = 0; $x < count($result); $x++)
        {
                $api_identifier = $result[$x]['identifier'];
                $api_id = $result[$x]['id'];
                $edit_link = admin_url('admin.php?page=wp-order-collector-controlpanel-api-keys-edit&edit-id=' . $api_id); 

                $html_as_string .= "
                <tr>
                    <td>
                        <h3>$api_identifier</h3>
                        
                    </td>
                    <td>
                        <a href=\"$edit_link\"><button>EDIT</button></a>
                    </td>
                </tr>
                ";
        }
    }
    if(count($result) < 2)
    {
        $add_api_section .= "
            <br>
            <a href=\"$add_link\">Add API credentials</a>
        ";
    }

    print(str_replace(
        array('%data%', '%add_api_section%'),
        array($html_as_string, $add_api_section),
        file_get_contents(__DIR__ . "/html/wp-order-collector-api-keys.html", true)
    ));
}

function wp_order_collector_controlpanel_api_keys_edit()
{
    if(!empty($_GET['edit-id']))
    {
        global $wpdb;
        global $wp_order_collector_table;
        
        $api_id = $_GET['edit-id'];
        $return_link = "admin.php?page=wp-order-collector-api-keys";
        $delete_link = "admin.php?page=wp-order-collector-controlpanel-api-keys-delete&del-id=$api_id";

        $sql = "SELECT * FROM $wp_order_collector_table WHERE id=$api_id";
        $result = $wpdb->get_results($sql, ARRAY_A);  
        
        $api_identifier = $result[0]['identifier'];
        $api_website = $result[0]['website'];
        $api_key = $result[0]['consumer_key'];
        $api_secret = $result[0]['consumer_secret'];
        $is_master = "";

        if($result[0]['is_master'] == 1)
        {
            $is_master = "checked";
        }
        
        print(str_replace(
            array("%identifier%","%website%", "%api_key%", "%api_secret%","%id%", "%is_master%", "%return_link%", "%delete_link%"),
            array($api_identifier, $api_website, $api_key, $api_secret, $api_id, $is_master, $return_link, $delete_link),
            file_get_contents(__DIR__ . "/html/wp-order-collector-api-keys-edit.html")
        ));
    }
    if(!empty($_POST['update_api_key']))
    {
        $id = $_POST['id'];
        $identifier = $_POST['identifier'];
        $website = $_POST['website'];
        $api_key = $_POST['api_key'];
        $api_secret = $_POST['api_secret'];
        $is_master = $_POST['is_master'];

        $result = $wpdb->get_results("SELECT * FROM $wp_order_collector_table WHERE is_master=1");

        if($is_master == 1 && count($result) > 0)
        {
            echo "ERROR: Already found a master API in database";
        }
        else
        {
            $result = $wpdb->update(
                $wp_order_collector_table,
                array(
                    'identifier'=>$identifier,
                    'website'=>$website,
                    'consumer_key'=>$api_key,
                    'consumer_secret'=>$api_secret,
                    'is_master' => $is_master
                ),
                array(
                    'id'=>$id
                    )
                );
                wp_redirect( admin_url($return_link),301 ); 
        }  
    }
}

function wp_order_collector_controlpanel_api_keys_add()
{
    global $wpdb;
    global $wp_order_collector_table;

    $return_link = "admin.php?page=wp-order-collector-api-keys";
    print(str_replace(
        array("%return_link%"),
        array($return_link),
        file_get_contents(__DIR__ . "/html/wp-order-collector-api-keys-add.html")
    ));

    if(!empty($_POST['add_api_key']))
    {
        $identifier = $_POST['identifier'];
        $website = $_POST['website'];
        $api_key = $_POST['api_key'];
        $api_secret = $_POST['api_secret'];
        $is_master = 0;
        if(!empty($_POST['is_master']))
        {
            $is_master = $_POST['is_master'];
        }

        $result = $wpdb->get_results("SELECT * FROM $wp_order_collector_table WHERE is_master=$is_master");

        if($is_master == 1 && count($result) > 0)
        {
            echo "ERROR: Already found a master API in database";
        }
        else if($is_master == 0 && count($result) > 0)
        {
            echo "ERROR: Only one client can be added";
        }
        else
        {
            $wpdb->insert(
                $wp_order_collector_table,
                array(
                    'identifier' => $identifier,
                    'website' => $website,
                    'consumer_key' => $api_key,
                    'consumer_secret' => $api_secret,
                    'is_master' => $is_master
                )
            );
            wp_redirect( admin_url($return_link),301 ); 
        }

    }
}

function wp_order_collector_controlpanel_api_keys_delete()
{
    global $wpdb;
    global $wp_order_collector_table;

    $return_link = "admin.php?page=wp-order-collector-api-keys";

    if(!empty($_GET['del-id']))
    {
        $id = $_GET['del-id'];

        $wpdb->delete(
            $wp_order_collector_table,
            array(
                'id' => $id
            )
        );
        wp_redirect( admin_url($return_link),301 ); 
    }

}


//Activation & uninstall functions
function wp_order_collector_activation_function() 
{
    global $wpdb;
    global $wp_order_collector_table;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $wp_order_collector_table (
        id int NOT NULL AUTO_INCREMENT,
        identifier varchar(256) NOT NULL,
        website varchar(256) NOT NULL,
        consumer_key varchar(256) NOT NULL,
        consumer_secret varchar(256) NOT NULL,
        is_master TINYINT NOT NULL,
        PRIMARY KEY  (id)
        ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

}

function wp_order_collector_uninstall_function() 
{
    global $wpdb;
    global $wp_order_collector_table;
    $sql = "DROP TABLE IF EXISTS $wp_order_collector_table;";
    $wpdb->query($sql);

}

add_action( "woocommerce_before_main_content", "update_product_stock");
function update_product_stock()
{
    $page_id = get_queried_object_id(); //Gets page id, from the query

    if(is_product())
    {

        custom_log("Updating stock");
        global $wp_order_collector_client;
        global $wp_order_collector_table;
        global $wp_order_collector_master_client;
        global $wpdb;
        
        
        if(!isset($wp_order_collector_master_client))
        {
            $wp_order_collector_master_client = wp_order_collector_create_master_client();
        }
        if(!isset($wp_order_collector_client))
        {
            $sql = "SELECT * FROM $wp_order_collector_table WHERE is_master=0";
            $result = $wpdb->get_results($sql, ARRAY_A);
            
            $wp_order_collector_client = wp_order_collector_get_client_from_id($result[0]['id']);
        }
        
        //Retrieves main product from client
        $product = $wp_order_collector_client->get("products/$page_id");
        $product = wc_sanitize_response($product);

        //Retrieves all variations from client
        $client_variations = $wp_order_collector_client->get('products/' . $product['id'] . "/variations");
        $client_variations = wc_sanitize_response($client_variations);

        custom_log(count($client_variations) . " variations found");

        //Generate search string for all matching sku's
        $variation_search_string = "?sku=" . $product['sku'];
        foreach($client_variations as $client_variations_key => $client_variations_value)
        {
            $variation_search_string .= "," . $client_variations_value['sku'];
            custom_log("Variation sku", $client_variations_value['sku']);
        }

        custom_log("Search string", $variation_search_string);
        
        //Retrieves all matching master variations including main product
        $master_variations = $wp_order_collector_master_client->get('products/'. $variation_search_string);
        $master_variations = wc_sanitize_response($master_variations);

        //Generate update array for main product
        $data = []; //empty array to put update values into
        foreach($master_variations as $master_variations_key => $master_variations_value)
        {
            if($master_variations_value['sku'] == $product['sku'])
            {
                $data = [
                    'stock_quantity' => $master_variations_value['stock_quantity']
                ];
            }
        }
        custom_log("Changing stock for " . $product['sku'] . " from " . $product['stock_quantity'] . " => " . $data['stock_quantity']);
        $wp_order_collector_client->put('products/' . $product['id'], $data);

        //Generate batch update array for variations
        $data = []; //emptying array to put update values into
        foreach($client_variations as $client_variations_key => $client_variations_value)
        {
            foreach($master_variations as $master_variations_key => $master_variations_value)
            {
                if($client_variations_value['sku'] == $master_variations_value['sku'])
                {
                    array_push($data, [
                        'id' => $client_variations_value['id'],
                        'stock_quantity' => $master_variations_value['stock_quantity'],
                        "backorders" => $master_variations_value['backorders'],
                        "backorders_allowed" => $master_variations_value['backorders_allowed'],
                        "backordered" => $master_variations_value['backordered'],
                        "stock_status"=> $master_variations_value['stock_status']
                    ]);
                }   
            }
        }
        $update_data = [ 'update' => $data];

        custom_log("update data", $update_data);
        $wp_order_collector_client->put('products/' . $product['id'] . "/variations/batch", $update_data);

        custom_log("Post meta", get_post_meta($page_id));
    }
}

//Creates master client
function wp_order_collector_create_master_client()
{
    global $wp_order_collector_table;
    global $wpdb;

    $sql =  "SELECT * FROM $wp_order_collector_table WHERE is_master=1";

    $result = $wpdb->get_results($sql, ARRAY_A);
    
    $website = $result[0]['website'];
    $consumer_key = $result[0]['consumer_key'];
    $consumer_secret = $result[0]['consumer_secret'];
    return new Client
    (
        "$website",
        "$consumer_key",
        "$consumer_secret",
        [
            'wp_api' => true,
            'version' => 'wc/v3'
        ]
    );
}

function wp_order_collector_get_client_from_id(int $id)
{
    
    global $wpdb;
    global $wp_order_collector_table;

    $sql =  "SELECT * FROM $wp_order_collector_table WHERE id=$id";

    $result = $wpdb->get_results($sql, ARRAY_A);
    
    $website = $result[0]['website'];
    $consumer_key = $result[0]['consumer_key'];
    $consumer_secret = $result[0]['consumer_secret'];

    $client = new Client
    (
        "$website",
        "$consumer_key",
        "$consumer_secret",
        [
            'wp_api' => true,
            'version' => 'wc/v3'
        ]
    );
    return $client;
}

function custom_log($title, $variable = NULL)
{
    $file = __DIR__ . '/custom_log.txt';
    $time = date('H:i:s - d-m-Y');

    if($variable != NULL)
    {
        file_put_contents($file, 
        "[$time] $title\n" .
        print_r($variable, true) . "\n\n", FILE_APPEND);
    }
    else{
        file_put_contents($file, 
        "[$time] $title\n" .
        print_r($variable, true) . "\n", FILE_APPEND);
    }
}

function wc_sanitize_response($response)
{
    return json_decode(json_encode($response), true);
}

add_action( "woocommerce_before_thankyou", "update_order_master");
function update_order_master($order_id)
{
    //Checks if the thankyou page have already been activated with the order. If it has, skip and don't update stock
    if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) 
    {
        global $wp_order_collector_master_client;
        global $wp_order_collector_client;
        global $wp_order_collector_table;
        global $wpdb;
        
        if(!isset($wp_order_collector_master_client))
        {
            $wp_order_collector_master_client = wp_order_collector_create_master_client();
        }
        if(!isset($wp_order_collector_client))
        {
            $sql = "SELECT * FROM $wp_order_collector_table WHERE is_master=0";
            $result = $wpdb->get_results($sql, ARRAY_A);
            
            $wp_order_collector_client = wp_order_collector_get_client_from_id($result[0]['id']);
        }
        
        $master_result = $wpdb->get_results("SELECT * FROM $wp_order_collector_table WHERE is_master=1", ARRAY_A);
        $master_result = wc_sanitize_response($master_result);
        
        $client_result = $wpdb->get_results("SELECT * FROM $wp_order_collector_table WHERE is_master=0", ARRAY_A);
        $client_result = wc_sanitize_response($client_result);
        
        
        
        $api_endpoint = $master_result[0]['website'] . "wp-json/wp-order-collector/v1/" . $client_result[0]['identifier'] . "/" . $order_id;
        custom_log("Endpoint", $api_endpoint);
        
        $api = new WP_Http();
        $api->get($api_endpoint);

        $order = wc_get_order( $order_id ); //Gets the order object
        $order->update_meta_data( '_thankyou_action_done', true ); //Updates the metadata for validation check
        $order->save(); //saves the object
    }
}