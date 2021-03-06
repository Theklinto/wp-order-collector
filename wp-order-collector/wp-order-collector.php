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

add_action( 'admin_enqueue_scripts', 'wp_order_collector_assets');
function wp_order_collector_assets() 
{
    wp_enqueue_style( "wp_order_collector_stylesheet",  plugin_dir_url(__FILE__) . "/wp_order_collector_stylesheet.css");
}

use Automattic\WooCommerce\Client;

//Registering the endpoint for api order call.
add_action( 'rest_api_init', function () {
    register_rest_route( 'wp-order-collector/v1', '/(?P<identifier>[\w]+)/(?P<orderid>[\d]+)', array(
      'methods' => 'GET',
      'callback' => 'wp_order_collector_update_stock',
      'permission_callback' => '__return_true'
    ) );
  } 
);


//Defining global variables used.
global $wp_order_collector_client;
global $wp_order_collector_all_clients;
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
    global $wpdb;
    global $wp_order_collector_table;
	//require_once('html/wp-order-collector-settings.html');
    $sql = "SELECT * FROM $wp_order_collector_table";
    $html_as_string = '';
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

    print(str_replace(
        array('%data%', "%add_link%"),
        array($html_as_string, $add_link),
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

        $result = $wpdb->get_results("SELECT * FROM $wp_order_collector_table WHERE is_master=1");

        if($is_master == 1 && count($result) > 0)
        {
            echo "ERROR: Already found a master API in database";
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

function wp_order_collector_create_all_clients()
{
    global $wp_order_collector_table;
    global $wpdb;
    $array = [];

    $sql = "SELECT * FROM $wp_order_collector_table WHERE is_master=0";
    $result = $wpdb->get_results($sql, ARRAY_A);

    for($x = 0; $x < count($result); $x++)
    {
        array_push($array, Wp_order_collector_get_client_from_identifier($result[$x]['identifier']));
    }

    return $array;
}

function wp_order_collector_update_stock($data)
{
    global $wp_order_collector_client;

    $order_id = $data['orderid'];
    $identifier= $data['identifier'];

    $wp_order_collector_client = wp_order_collector_get_client_from_identifier($identifier);
    $order = $wp_order_collector_client->get("orders/$order_id");

    $decoded_order = json_decode(json_encode($order), true); //Needs to be encoded and then decoded since WC api returns an "json object" and the method only accepts a "json string".

    custom_log("Going through lineitems");
    foreach( $decoded_order['line_items'] as $item) 
    {
        custom_log("Lineitem", $item);
        $product_sku = $item['sku'];
        custom_log("sku", $item['sku']);
        $quantity = $item['quantity'];  
        custom_log("Quantity", $item['quantity']);
        wp_order_collector_update_stock_from_sku($product_sku, $quantity);
    }
}
function wp_order_collector_get_all_clients_to_menu()
{
    global $wpdb;
    global $wp_order_collector_table;
    $sql = "SELECT * FROM $wp_order_collector_table WHERE is_master=0";
    $clients = [];

    $result = $wpdb->get_results($sql, ARRAY_A);

    foreach($result as $result_key => $result_value)
    {
        array_push($clients, [
            'id' => $result_value['id'],
            'identifier' => $result_value['identifier'],
            'website' => $result_value['website']
        ]);
    }

    return $clients;
}

function wp_order_collector_get_client_from_identifier(string $identifier)
{
    
    global $wpdb;
    global $wp_order_collector_table;

    $sql =  "SELECT * FROM $wp_order_collector_table WHERE identifier='$identifier'";

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
            'version' => 'wc/v3',
            'timeout' => 400
        ]
    );
    return $client;
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
            'version' => 'wc/v3',
            'timeout' => 400
        ]
    );
    return $client;
}

function wp_order_collector_update_stock_from_sku($product_sku, $quantity)
{
    global $wp_order_collector_master_client;
    $data = [];
    if(!isset($wp_order_collector_master_client))
    {
        $wp_order_collector_master_client = wp_order_collector_create_master_client();
    }

    $endpoint = "products/?sku=$product_sku";

    $product = $wp_order_collector_master_client->get($endpoint);
    $decoded_product = json_decode(json_encode($product), true);
    if(!empty($decoded_product))
    {
        custom_log("Product with sku found", $decoded_product);
        
        $current_stock = $decoded_product[0]['stock_quantity'];
        $backorders_allowed = $decoded_product[0]['backorders_allowed'];
        $product_id = $decoded_product[0]['id'];
        
        custom_log("Current stock", $current_stock);
        custom_log("Backorders allowed", $backorders_allowed);
        
        if((is_int($current_stock) AND $current_stock > 0) OR ($current_stock <= 0 AND isset($backorders_allowed)))
        {
            $new_stock = $current_stock - $quantity;
            
            custom_log("Updating stock,\n current: " . $current_stock . "\norder quantity: " . $quantity . "\nNew stock: " . $new_stock);
            
            $data = [
                'stock_quantity' => $new_stock
            ];
        }
        else
        {
            $data = [
                'stock_quantity' => 0
            ];
        }
        
        $endpoint = 'products/' . $product_id;
        custom_log("Trying to insert into $endpoint", $data);
        wc_update_product_stock( $product_id, $new_stock);
        Custom_log("updated product", $wp_order_collector_master_client->get($endpoint) );
    }
}

add_action( 'woocommerce_process_product_meta', 'wp_order_collector_create_new_product',1000,2 );
function wp_order_collector_create_new_product($post_id, $post) 
{
    global $wp_order_collector_master_client;

    $endpoint = "products/$post_id";

    $wp_order_collector_master_client = wp_order_collector_create_master_client();

    $product = $wp_order_collector_master_client->get($endpoint); 
    $decoded_product = json_decode(json_encode($product), true);

    //Gets all webshops to alter
    $_wp_order_collector_clients_to_alter = [];
    $wp_order_collector_clients_to_alter = [];
    $post_meta = get_post_meta($post_id);
    foreach($post_meta as $post_meta_key => $post_meta_value)
    {
        $pattern = "#^(wp_order_collector_webshop)(\d+)$#";
        if(preg_match($pattern, $post_meta_key) && $post_meta_value[0] == "yes")
        {
            custom_log("Meta key found", $post_meta_key);
            custom_log("vaulue", $post_meta_value[0]);
            $pattern = "wp_order_collector_webshop";
            $_ = explode($pattern, (string)$post_meta_key);
            array_push($_wp_order_collector_clients_to_alter, $_[1]);
        }
    }
    foreach($_wp_order_collector_clients_to_alter as $_wp_order_collector_clients_to_alter_key => $_wp_order_collector_clients_to_alter_value)
    {
        custom_log("Client id", $_wp_order_collector_clients_to_alter);
        array_push(
            $wp_order_collector_clients_to_alter,
            wp_order_collector_get_client_from_id($_wp_order_collector_clients_to_alter_value)
        );
    }

    custom_log("All clients to alter", $wp_order_collector_clients_to_alter);
    
    $product_sku = $decoded_product['sku'];

    $endpoint = "products/?sku=$product_sku";

    for($x = 0; $x < count($wp_order_collector_clients_to_alter); $x++)
    {
        $exists = $wp_order_collector_clients_to_alter[$x]->get($endpoint);

        $exists = json_decode(json_encode($exists));

        $data = $decoded_product;
        $data = wp_order_collector_remove_tags($data, array(
                'related_ids',
                'meta_data',
                '_links',
                'price_html',
                'grouped_products',
                'permalink',
                'id'
            ));
            if(count($exists) == 0) //Checks if a product is already created
            {
                foreach($data['images'] as $image_key => $image)
                {
                    $image_array = explode('/', $image['src']);
                    $image_file = $image_array[count($image_array) - 1];
                    custom_log("Searching for image", $image_file);
                    $image_found = wc_sanitize_response($wp_order_collector_clients_to_alter[$x]->get("images/image=" . $image_file));

                    if(count($image_found) > 0)
                    {
                        custom_log("Found image", $image_found);
                        $data['images'][$image_key]['id'] = $image_found['id'];
                        $data['images'][$image_key] = wp_order_collector_remove_tags($data['images'][$image_key], [
                            'date_created',
                            'date_created_gmt',
                            'date_modified',
                            'date_modified_gmt',
                            'src',
                            'name',
                            'alt'
                        ]);
                        custom_log("Image formatted", $data['images'][$image_key]);
                    }
                    else
                    {
                        custom_log("Image not found");
                        $data['images'][$image_key] = wp_order_collector_remove_tags($data['images'][$image_key], [
                            'date_created',
                            'date_created_gmt',
                            'date_modified',
                            'date_modified_gmt',
                            'id'
                        ]);
                    }
                }
            }
            else{ //If it is created, no description, images or titles should be changed.
                $data = wp_order_collector_remove_tags($data, array(
                    'name',
                    'slug',
                    'images',
                    'short_description',
                    'description',
                    'date_created_gmt',
                    'date_created',
                ));
            }

            $added_attributes = [];
            $all_variations = [];
            if(!empty($data['attributes'])) //Checks if there are any attributes and variations
            {
                custom_log("Product contains attributes");
                $client_attributes = $wp_order_collector_clients_to_alter[$x]->get('products/attributes'); //Gets all attributes from webshop
                $client_attributes = wc_sanitize_response($client_attributes);
                custom_log("Attributes", $client_attributes);

                foreach($data['attributes'] as $key => $product_attribute) //Iterates through product attributes
                {
                    custom_log("Looking for attributes associated to id: " . $product_attribute['id']);
                    custom_log("Attributes", $product_attribute);
                    $attribute = $wp_order_collector_master_client->get("products/attributes/" . $product_attribute['id']); //Gets the full attribute from the product
                    $attribute = json_decode(json_encode($attribute), true);
                    custom_log("Attributes found",$attribute);
                    $found_attribute = []; //Array to push results to

                    if(count($client_attributes) > 0) //checks if the client has any attributes at all.
                    {
                        custom_log("Found multiple attribues in client");
                        //Loops through all attributes
                        foreach($client_attributes as $attribute_key => $current)
                        {
                            $current = json_decode(json_encode($current), true);
                            if($current['slug'] == $attribute['slug']) //Checks for a matching slug between webshop and the product
                            {
                                custom_log("Matching attribute found", $current['name']);
                                if(empty($found_attribute)) //If no previous results were found
                                {
                                    $data['attributes'][$key]['id'] = $current['id'];
                                    array_push($found_attribute, array("id" => $current['id'])); //adds the found 'id' to the array
                                    array_push($added_attributes, ['name' => $current['name'], 'id' => $current['id']]);
                                    //Checks if all terms are pressent
                                    //Get terms from master
                                    $master_terms = $wp_order_collector_master_client->get('products/attributes/' . $attribute['id'] . '/terms');//Gets all terms from master
                                    $master_terms = json_decode(json_encode($master_terms), true); //Deocdes json to php array
                                    foreach($master_terms as $master_terms_key => $master_terms_value)
                                    {
                                        $master_terms[$master_terms_key] = wp_order_collector_remove_tags($master_terms_value, array(
                                            'count',
                                            '_links',
                                            'id'
                                        ) );
                                    }
                                    
                                    //get terms from client
                                    $client_terms = $wp_order_collector_clients_to_alter[$x]->get('products/attributes/' . $current['id'] . '/terms');//Gets all terms from client
                                    $client_terms = json_decode(json_encode($client_terms), true); //Decodes json to php array
                                    foreach($client_terms as $client_terms_key => $client_terms_value)
                                    {
                                        $client_terms[$client_terms_key] = wp_order_collector_remove_tags($client_terms_value, array(
                                            'count',
                                            '_links',
                                            'id'
                                        ) );
                                    }

                                    foreach($client_terms as $client_terms_key => $client_terms_value)
                                    {
                                        foreach($master_terms as $master_terms_key => $master_terms_value)
                                        {
                                            if($client_terms_value == $master_terms_value)
                                            {
                                                unset($client_terms[$client_terms_key]);
                                                unset($master_terms[$master_terms_key]);
                                            }
                                        }
                                    }
                                    custom_log("Master attribute terms the client doesn't have", $master_terms);
                                    if(count($master_terms) > 0)
                                    {
                                        foreach($master_terms as $master_terms_key => $master_terms_value) //sanitizing terms and adding them to the attribute
                                        {
                                            $terms_to_insert = [ //Creates the term with the minimum values
                                                'name' => $master_terms_value['name'],
                                                'slug' => $master_terms_value['slug'],
                                                'description' => $master_terms_value['description'],
                                                'menu_order' => $master_terms_value['menu_order']
                                            ];
                                            $wp_order_collector_clients_to_alter[$x]->post('products/attributes/' . $current['id'] . '/terms', $terms_to_insert); //Inserts the term
                                            
                                            custom_log("Added term to following attribute: " . $attribute['name']);
                                            custom_log("Inserted", $terms_to_insert);
                                        }
                                    }
                                }
                                else //Throws an error if multiple attributes were found.
                                {
                                        trigger_error('Duplicate attributes found');
                                }
                            }
                        }
                        //If no matching attribute were found
                        if(empty($found_attribute))
                        {
                            custom_log("No matching attribute were found, adding it...");

                            //Sanitizing attribute and inserting it
                            $attribute_to_insert = wp_order_collector_remove_tags($attribute, array(
                                'id',
                                '_links'

                            ));
                            $inserted_attribute = $wp_order_collector_clients_to_alter[$x]->post("products/attributes", $attribute_to_insert  );
                            $inserted_attribute = json_decode(json_encode($inserted_attribute), true);
                            array_push($added_attributes, ['name' => $inserted_attribute['name'], 'id' => $inserted_attribute['id']]);
                            $data['attributes'][$key]['id'] = $inserted_attribute['id'];

                            //Collecting all terms from attribute
                            $master_terms = $wp_order_collector_master_client->get('products/attributes/' . $attribute['id'] . '/terms');//Gets all terms from master
                            $master_terms = json_decode(json_encode($master_terms), true); //Deocdes json to php array
                            foreach($master_terms as $master_terms_key => $master_terms_value) //sanitizing terms and adding them to the attribute
                            {
                                $master_terms[$master_terms_key] = wp_order_collector_remove_tags($master_terms_value, array(
                                    'count',
                                    '_links',
                                    'id'
                                ) );
                                $terms_to_insert = [ //Creates the term with the minimum values
                                    'name' => $master_terms_value['name'],
                                    'slug' => $master_terms_value['slug'],
                                    'description' => $master_terms_value['description'],
                                    'menu_order' => $master_terms_value['menu_order']
                                ];
                                $wp_order_collector_clients_to_alter[$x]->post('products/attributes/' . $inserted_attribute['id'] . '/terms', $terms_to_insert); //Inserts the term

                                custom_log("Added term to following attribute: " . $attribute['name']);
                                custom_log("Inserted", $terms_to_insert);
                            }
                        }                        
                    }

                    //If the client doesn't have any attributes at all
                    if(count($client_attributes) == 0)
                    {
                        custom_log("No client attributes found, adding from master");
                        $full_attribute = $wp_order_collector_master_client->get("products/attributes/" . $product_attribute['id']); //Gets the attribute from API
                        $full_attribute = json_decode(json_encode($full_attribute), true); //Converts it from StdObject to array

                        $formatted_array = wp_order_collector_remove_tags($full_attribute, array("_links", "id")); //Removes tags
                        custom_log("Adding following attributes to the client",$formatted_array);
                        $inserted_attribute = $wp_order_collector_clients_to_alter[$x]->post('products/attributes', $formatted_array); //Posts the attribute to the client
                        $inserted_attribute = json_decode(json_encode($inserted_attribute), true);
                        array_push($added_attributes, ['name' => $$inserted_attribute['name'], 'id' => $inserted_attribute['id']]);
                        $data['attributes'][$key]['id'] = $inserted_attribute['id'];

                        //If no attribute are found, no terms would be found either. So it posts all the terms to the attribute
                        $master_terms = $wp_order_collector_master_client->get('products/attributes/' . $product_attribute['id'] . '/terms');//Gets all terms from master
                        $master_terms = json_decode(json_encode($master_terms), true);

                        if(!empty($master_terms))
                        {
                            foreach($master_terms as $term) //Loops through all terms from the attribute
                            {
                                $terms_to_insert = [ //Creates the term with the minimum values
                                    'name' => $term['name'],
                                    'slug' => $term['slug'],
                                    'description' => $term['description'],
                                    'menu_order' => $term['menu_order']
                                ];
                                $wp_order_collector_clients_to_alter[$x]->post('products/attributes/' . $inserted_attribute['id'] . '/terms', $terms_to_insert); //Inserts the term
                                custom_log("Added term to following attribute: " . $inserted_attribute['name']);
                                custom_log("Inserted", $terms_to_insert);
                            }
                        }

                        custom_log("Updated product refference from id: " . $data['attributes'][$key]['id'] . " to id: " . $inserted_attribute['id']);
                        $data['attributes'][$key]['id'] = $inserted_attribute['id'];
                    }
                }
            }

            //Checks if the product contains any shipping class
            wp_order_collector_check_for_shipping_class($wp_order_collector_clients_to_alter[$x], $data);

            //Check if the product contains any categories
            if(!empty($data['categories']))
            {
                $category_ids = [];
                $categories_to_add = [];
                $categories_dictionary = [];
                $master_categories = wp_order_collector_get_categories_from_product(wc_get_product($post_id)); //Fetches all categories associated with the product
                $client_categories = $wp_order_collector_clients_to_alter[$x]->get("products/categories"); //Fetches all client categories
                $client_categories = wc_sanitize_response($client_categories);

                //Loops through all categories to check for missing categories in client
                foreach($master_categories as $master_categories_key => $master_categories_value)
                {
                    //Create dictionary array for category
                    $categories_dictionary[$master_categories_value['term_id']] = [
                        $master_categories_value['slug'] => 'slug',
                        $master_categories_value['parent'] => 'old_parent',
                        $master_categories_value['term_id'] => 'old_id'
                    ];
                    custom_log("Searching for category: " . $master_categories_value['slug']);
                    //Check if category exsist
                    /*
                    $found_category = array_search($master_categories_value['slug'], array_column($client_categories, 'slug'));
                    custom_log("Categories in client", $client_categories);
                    if(!empty($found_category))
                    {
                        //if category exists find and extract slug and id and insert into the dictionary
                        foreach($client_categories as $client_categories_key => $client_categories_value)
                        {
                            if($client_categories_value['slug'] == $master_categories_value['slug'])
                            {
                                custom_log("Found category: ",$client_categories_value);
                                $categories_dictionary[$master_categories_value['term_id']][$client_categories_value['id']] = 'new_id';
                                break;
                            }
                        }
                    }
                    */

                    $found_category = false;
                    //if category exists find and extract slug and id and insert into the dictionary
                    foreach($client_categories as $client_categories_key => $client_categories_value)
                    {
                        if($client_categories_value['slug'] == $master_categories_value['slug'])
                        {
                            custom_log("Found category: ",$client_categories_value);
                            $categories_dictionary[$master_categories_value['term_id']][$client_categories_value['id']] = 'new_id';
                            $found_category = true;
                            break;
                        }
                    }

                    if(!$found_category)
                    {
                        //if category don't exist add to create array
                        custom_log("Category not found");
                        array_push($categories_to_add, [
                            'slug' => $master_categories_value['slug'],
                            'name' => $master_categories_value['name']
                        ]);
                        $categories_dictionary[$master_categories_value['term_id']] = [
                            $master_categories_value['slug'] => 'slug',
                            $master_categories_value['parent'] => 'old_parent',
                            $master_categories_value['term_id'] => 'old_id'
                        ];
                    }
                }

                //If anyone where missing add them now
                if(!empty($categories_to_add))
                {
                    $inserted_categories = wc_sanitize_response($wp_order_collector_clients_to_alter[$x]->post("products/categories/batch", [
                        'create' => $categories_to_add
                    ]));

                    //loops through inserted categories to add to list
                    foreach($inserted_categories['create'] as $inserted_categories_key => $inserted_categories_value)
                    {
                        foreach($master_categories as $master_categories_key => $master_categories_value)
                        {
                            custom_log("Master category", $master_categories_value);
                            custom_log("Inserted category", $inserted_categories_value);
                            if($master_categories_value['slug'] == $inserted_categories_value['slug'])
                            {
                                $categories_dictionary[$master_categories_value['term_id']][$inserted_categories_value['id']] = 'new_id';
                            }
                        }
                    }
                }

                custom_log("Product before categories", $data);

                //Loop through all added categories and corrects their parent
                $update_parents = [];
                foreach($master_categories as $master_categories_key => $master_categories_value)
                {
                    custom_log("category value", $master_categories_value);
                    //Gets the new parent id
                    foreach($categories_dictionary as $categories_dictionary_key => $categories_dictionary_value)
                    {
                        if($master_categories_value['parent'] == array_search('old_parent', $categories_dictionary_value))
                        {
                            $parent_id = 0;
                            if($categories_dictionary[$master_categories_value['parent']])
                            {
                                $parent_id = array_search('new_id', $categories_dictionary[$master_categories_value['parent']]);
                            }
                            array_push($update_parents, [
                                'id' => array_search('new_id', $categories_dictionary_value),
                                'parent' => $parent_id
                            ]);
                            
                            array_push($category_ids, array_search('new_id', $categories_dictionary_value));
                        }
                        foreach($data['categories'] as $category_key => $category_value)
                        {
                            if($category_value['id'] == array_search('old_id', $categories_dictionary_value))
                            {
                                $new_category_id = (int)array_search('new_id', $categories_dictionary_value);
                                $data['categories'][$category_key]['id'] = $new_category_id;
                            }
                        }
                    }
                }
                custom_log("Category dictionary", $categories_dictionary);
                custom_log("Update array", $update_parents);

                if(!empty($update_parents))
                {
                    custom_log("Updating parent ids", $update_parents);
                    $wp_order_collector_clients_to_alter[$x]->post("products/categories/batch", [
                        'update' => $update_parents
                    ]);
                }
            }

            //Checks if upsells are attached
            if(count($data['upsell_ids']) > 0)
            {
                $upsell_array = [];
                custom_log("Upsells found");
                foreach($data['upsell_ids'] as $upsell_key => $upsell_value)
                {
                    custom_log("upsell id: " . $upsell_value);
                    //Start by checking if the product exists on client site
                    $master_upsell = wc_get_product($upsell_value); //Gets the product from master
                    custom_log("Cross sell sku", $master_upsell->get_sku());
                    $client_upsell = $wp_order_collector_clients_to_alter[$x]->get("products?sku=" . (string)$master_upsell->get_sku());
                    custom_log("Client upsell", $client_upsell);
                    custom_log("Master meta data", get_post_meta($master_upsell->get_id()));
                    $client_upsell = wc_sanitize_response($client_upsell);
                    if(count($client_upsell) > 0) //Checks if the array returned is empty, if it exists get the id.
                    {
                        array_push($upsell_array, $client_upsell[0]['id']);
                        custom_log("upsell id changed: " . $upsell_value . " => " . $client_upsell[0]['id']);
                    }
                    else
                    {
                        custom_log("No matching product found");
                    }
                }
                $data['upsell_ids'] = $upsell_array;
            }
            
            //Checks if cross sells are attached
            if(count($data['cross_sell_ids']) > 0)
            {
                $crosssell_array = [];
                custom_log("Cross sells found");
                foreach($data['cross_sell_ids'] as $crosssell_key => $crosssell_value)
                {
                    custom_log("crosssell id: " . $crosssell_value);
                    //Start by checking if the product exists on client site
                    $master_crosssell = wc_get_product($crosssell_value); //Gets the product from master
                    custom_log("Cross sell sku", $master_crosssell->get_sku());
                    $client_crosssell = $wp_order_collector_clients_to_alter[$x]->get("products?sku=" . $master_crosssell->get_sku());
                    $client_crosssell = wc_sanitize_response($client_crosssell);
                    if(!empty($client_crosssell)) //Checks if the array returned is empty, if it exists get the id.
                    {
                        array_push($crosssell_array, $client_crosssell[0]['id']);
                        custom_log("crosssell id changed: " . $crosssell_value . " => " . $client_crosssell[0]['id']);
                    }
                    else
                    {
                        custom_log("No matching product found");
                    }
                }
                $data['cross_sell_ids'] = $crosssell_array;
            }

            //Posts the product to the client
            if(count($exists) == 0)
            {
                custom_log("Trying to insert product", $data);
                $inserted_product = $wp_order_collector_clients_to_alter[$x]->post('products', $data);
                $inserted_product = json_decode(json_encode($inserted_product), true);
                custom_log("Product created!");
            }
            else{ //retrieve product and update it
                $exists = wc_sanitize_response($exists);
                custom_log("Exists product", $exists);
                custom_log("updated product", $data);
                $inserted_product = $wp_order_collector_clients_to_alter[$x]->put('products/' . $exists[0]['id'], $data);
                $inserted_product = json_decode(json_encode($inserted_product), true);
                custom_log("Product updated!");
            }
            
            array_push($all_variations, [$inserted_product['id']]);
            custom_log("Added attributes", $added_attributes);

            //Checks for the variations. If any found, they are created and linked to the parrent.
            if(!empty($data['variations'])) //$variation = variation id
            {
                custom_log("Found variations");

                if(count($exists[0]) > 0) //Only gets variations if a product exists
                {
                    $_variation_exists = $wp_order_collector_clients_to_alter[$x]->get('products/' . $exists[0]['id'] . '/variations'); //Retrieves all variations 
                    $_variation_exists = wc_sanitize_response($_variation_exists);
                }


                foreach($data['variations'] as $variation_key => $variation)
                {
                    custom_log("Adding variation id: $variation");
                    $full_variation = $wp_order_collector_master_client->get("products/$variation");
                    $full_variation = json_decode(json_encode($full_variation), true);
                    custom_log("Found variation", $full_variation);
                    $variation_exists = false;

                    //Check if variation exists
                    if(count($exists) > 0)
                    {
                        if($_variation_exists > 0) //Checks if product contains any variations.
                        {
                            foreach($_variation_exists as $_variation_exists_key => $_variation_exists_value)
                            {
                                if($_variation_exists_value['sku'] == $full_variation['sku']) //If a matching SKU is found, update variation instead.
                                {
                                    $full_variation['id'] = $_variation_exists_value['id'];
                                    $variation_exists = true;
                                }
                            }
                        }
                    }
                    $full_variation_id = $full_variation['id']; //saves the id before removing it from data
                    $full_variation = wp_order_collector_remove_tags($full_variation, array(
                        'related_ids',
                        'meta_data',
                        '_links',
                        'price_html',
                        'grouped_products',
                        'permalink',
                        'id',
                    ));

                    if(!empty($variation_exists))
                    {
                        $data = wp_order_collector_remove_tags($data, array(
                            'name',
                            'slug',
                            'short_description',
                            'description',
                            'date_created_gmt',
                            'date_created',
                            'parent_id',
                        ));
                        
                    }
                    else
                    {
                        $full_variation['parent_id'] = $inserted_product['id'];
                        $full_variation['manage_stock'] = true;
                    }

                    //  Variations can only contain one image, and aren't formated correctly when recieved from API.
                    //  An array of images are recieved, but it only accepts one image.

                    //Sets variation image
                    $image = $full_variation['images']; //Gets the array of images

                    $image_array = explode('/', $image[0]['src']);
                    $image_file = $image_array[count($image_array) - 1];
                    $image_found = wc_sanitize_response($wp_order_collector_clients_to_alter[$x]->get("images/image=" . $image_file));
                    
                    $new_image = [];
                    if(count($image_found) > 0)
                    {
                        $new_image = [
                            'id' => $image_found['id']
                        ];
                    }
                    else
                    {
                        $new_image = [
                            'src' => $image[0]['src'],
                            'alt' => $image[0]['alt'],
                            'name' => $image[0]['name']
                        ];
                    }
                    unset($full_variation['images']); //Removes the key from variation
                    //Replaces the image array with a single image
                    //NOTICE: when recieved the key is 'images' but it accepts 'image' singular
                    //If no images were found, WooCommerce will automaticly take the products image
                    $full_variation['image'] = $new_image;
                    
                    foreach($full_variation['attributes'] as $attribute_key => $attributes)
                    {
                        custom_log("attribute:", $attributes); //Attribute to search for
                        $found_added_attribute = "";
                        custom_log("Seraching for: " . $attributes['name']);
                        
                        foreach($added_attributes as $added_attributes_key => $added_attributes_value)
                        {
                            $found_added_attribute = array_search($attributes['name'], $added_attributes[$added_attributes_key]);
                            
                            if(!empty($found_added_attribute))
                            {
                                custom_log("Found attribute: " . $added_attributes[$added_attributes_key][$found_added_attribute]);
                                custom_log("Changed variation attribute: " . $attributes['name'] . "\n from id: " . $full_variation['attributes'][$attribute_key]['id'] . " to id: " . $added_attributes[$added_attributes_key]['id']);
                                $full_variation['attributes'][$attribute_key]['id'] = $added_attributes[$added_attributes_key]['id']; //Sets the attribute id
                                $found_added_attribute = ""; //Resets variable
                                custom_log("New attribute", $full_variation['attributes'][$attribute_key]);
                            }
                        }
                    }
                    
                    //Checks if the variation contains any shipping class
                    wp_order_collector_check_for_shipping_class($wp_order_collector_clients_to_alter[$x], $full_variation);
                    
                    if($variation_exists)
                    {
                        custom_log("Updated variation", $full_variation);
                        //$full_variation['regular_price'] = (string)$full_variation['regular_price']; //explicit conversion, since it has to be a string, and it is turned into int automaticly.
                        //$full_variation['sale_price'] = (string)$full_variation['sale_price'];
                        $wp_order_collector_clients_to_alter[$x]->put('products/' . $inserted_product['id'] .'/variations/' . $full_variation_id, $full_variation);
                        array_push($all_variations, [$full_variation_id]);
                    }
                    else
                    {
                        custom_log("Variation to insert", $full_variation);
                        $inserted_variation = $wp_order_collector_clients_to_alter[$x]->post('products/' . $inserted_product['id'] .'/variations', $full_variation);
                        $inserted_variation = json_decode(json_encode($inserted_variation), true);
                        array_push($all_variations, [$inserted_variation['id']]);
                    }
                }
                
                custom_log('All variations', $all_variations);
                
                //Last add all variation id's to main product and variation products.
                $new_data = [
                    'variations' => $all_variations
                ];
                
                $wp_order_collector_clients_to_alter[$x]->put("products/" . $inserted_product['id'], $new_data);
        }
    }   
}

//Functions takes a WC_Product and returns all the categories in an array
function wp_order_collector_get_categories_from_product(WC_Product $product)
{
    $category_ids = $product->get_category_ids();
    $categories = [];
    foreach($category_ids as $category_ids_key => $category_ids_value) //Loops through the ids and fetches the categories
    {
        array_push($categories, get_term_by('id', $category_ids_value, 'product_cat')); //Uses 'product_cat' to search in categories
    }

    //Loops through all categories and check for their parrents
    foreach($categories as $categories_key => $categories_value)
    {
        if($categories_value->parent != 0) //Checks if the category has any parrent
        {
            $parent_id = $categories_value->parent; //Sets the parent id
            while($parent_id != 0)
            {
                $parent_category = get_term_by('id', $parent_id, 'product_cat');
                array_push($categories, $parent_category);
                $parent_id = $parent_category->parent;

            }
        }
    }
    //Checks for duplicates and return a new array without any duplicates
    $categories = wc_sanitize_response($categories); 
    $categories = array_unique($categories, SORT_REGULAR);

    return $categories;
}

function wp_order_collector_remove_tags($array, $tags)
{
    foreach($tags as $key => $value)
    {
        unset($array[$value]);
    }
    return $array;
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

function wp_order_collector_check_for_shipping_class($client, $haystack )
{
    global $wp_order_collector_master_client;

    if(!empty($haystack['shipping_class']))
    {
        custom_log("Shipping class found");
        $shipping_class = $client->get("products/shipping_classes");
        $shipping_class = json_decode(json_encode($shipping_class), true);

        if(count($shipping_class) > 0)
        {
            $shipping_class_found = [];
            custom_log("Following shipping classes found in client:", $shipping_class);
            foreach($shipping_class as $shipping_class_key => $shipping_class_value)
            {
                if($shipping_class_value['slug'] == $haystack['shipping_class'])
                {
                    $shipping_class_found = ['id' => $shipping_class_value['id']];
                }
            }

            if(count($shipping_class_found) == 1)
            {
                custom_log("Matching shipping class found");
                $haystack['shipping_class_id'] = $shipping_class_found['id'];
            }
            else if(count($shipping_class_found) == 0)
            {
                custom_log("No matching shipping class found");
                $shipping_class_to_insert = $wp_order_collector_master_client->get("products/shipping_classes/" . $haystack['shipping_class_id']);
                $shipping_class_to_insert = json_decode(json_encode($shipping_class_to_insert), true);

                $shipping_class_to_insert = wp_order_collector_remove_tags($shipping_class_to_insert, [
                    'count',
                    '_links',
                    'id'
                ]);
                $shipping_class_inserted = $client->post("products/shipping_classes", $shipping_class_to_insert);
                $shipping_class_inserted = json_decode(json_encode($shipping_class_inserted), true);
                custom_log("Shipping class inserted", $shipping_class_inserted);
                $haystack['shipping_class_id'] = $shipping_class_inserted['id'];
            }
        }
        else
        {
            custom_log("No shipping classes found");
            $shipping_class_to_insert = $wp_order_collector_master_client->get("products/shipping_classes/" . $haystack['shipping_class_id']);
            $shipping_class_to_insert = json_decode(json_encode($shipping_class_to_insert), true);

            $shipping_class_to_insert = wp_order_collector_remove_tags($shipping_class_to_insert, [
                'count',
                '_links',
                'id'
            ]);
            $shipping_class_inserted = $client->post("products/shipping_classes", $shipping_class_to_insert);
            custom_log("Shipping class inserted", $shipping_class_inserted);
            $haystack['shipping_class_id'] = $shipping_class_inserted['id'];
        }
    }
}

function wc_sanitize_response($response)
{
    return json_decode(json_encode($response), true);
}

//Filter to increase the limit for post meta data
add_filter( 'postmeta_form_limit', function( $limit ) {
    return 100;
} );
//Adds a new tab
add_filter( 'woocommerce_product_data_tabs', 'add_wp_order_collector_update_tab' , 99 , 1 );
// Display Fields
add_action('woocommerce_product_data_panels', 'wp_order_collector_product_custom_fields');
// Save Fields
add_action('woocommerce_process_product_meta', 'wp_order_collector_product_custom_fields_save');

function add_wp_order_collector_update_tab( $product_data_tabs ) {
    $product_data_tabs['wp_order_collector_tab'] = array
    (
        'label' => __( 'WP Order Collector', 'my_text_domain' ),
        'target' => 'wp_order_collector_update_menu',
    );
    return $product_data_tabs;
}

function wp_order_collector_product_custom_fields()
{
    global $woocommerce, $post;
    
    $clients = wp_order_collector_get_all_clients_to_menu();
    
    echo '<div id="wp_order_collector_update_menu" class="panel woocommerce_options_panel">';
    echo '<h3 style="padding-left: 10px;">Which webshops to update</h3>';
    //Generates a checkbox for every webshop

    foreach($clients as $clients_key => $clients_value)
    {
        custom_log("Generating field for id: ", $clients_value['id']);
        //$values = get_post_meta( $post->ID, "wp_order_collector_webshop" . $clients_value['id']);
        woocommerce_wp_checkbox(
            array(
                'id'            => 'wp_order_collector_webshop' . $clients_value['id'],
                'label'         => __($clients_value['website'], 'woocommerce'),
                'value'         => '0'
            )
        );
    }
    echo '</div>';
}

function wp_order_collector_product_custom_fields_save($post_id)
{
    custom_log("Saving fields in post: " . $post_id);
    $clients = wp_order_collector_get_all_clients_to_menu();

    //Looks for which clients are set, and remembers them
    foreach($clients as $clients_key => $clients_value)
    {

        $value = isset($_POST['wp_order_collector_webshop' . $clients_value['id']]) ? "yes" : "";
        update_post_meta($post_id, 'wp_order_collector_webshop' . $clients_value['id'], $value);
        custom_log("Meta key updated: " . 'wp_order_collector_webshop' . $clients_value['id'] . " -> " . (empty($value) ? "no" : $value));
    }

    custom_log("Post", get_post_meta($post_id));

    //Update product meta
}