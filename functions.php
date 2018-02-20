<?php

function my_theme_enqueue_styles() {
  $parent_style = 'lawyers-attorneys-style';

  // wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
  wp_enqueue_style( 'child-style',
                    get_stylesheet_directory_uri() . '/style.css',
                    array( $parent_style ),
                    wp_get_theme()->get('Version')
                    );
}
add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );

function child_styles() {
	wp_enqueue_style(
        'my-child-theme-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('front-all'),
        false,
        'all'
                     );
}
add_action('wp_enqueue_scripts', 'child_styles', 11);


// Add WPCustomArea Instructions to Completed Order confirmation email
// instructions: https://www.sellwithwp.com/customizing-woocommerce-order-emails/
// add_action( actionName, functionName, Priority, ArgumentCount )
function add_order_email_instructions( $order ) {
  echo "<p>Desde ahora puedes ver el estado y toda la documentación de tu pedido en nuestra <a href=\"https://agrupab.com/escritorio\">Area de Clientes</a></p>";
}
add_action('woocommerce_email_before_order_table', 'add_order_email_instructions', 10, 1);


// Save Project in WPCustomerArea after a product has been sold
// action: woocommerce_order_status_completed ( maybe woocommerce_payment_complete)
add_action('woocommerce_order_status_completed', 'create_wp_customer_area_project' );

function create_wp_customer_area_project( $order_id ) {
  $order = wc_get_order( $order_id );

  $project_id = add_project( $order );
  add_tasks( $order, $project_id );
  add_invoice( $order, $project_id );
}

function add_tasks( $order, $project_id ) {
  $user = $order->get_user();
  if ( $user ) {
    $items = $order->get_items();

    // create task post
    $new_tasklist = array(
        'post_author' => 1,
        'post_title' => $user->user_email . " : servicios",
        'post_content' => '',
        'post_type' => 'cuar_tasklist',
        'post_status' => 'publish'
                          );
    $tasklist_result = wp_insert_post($new_tasklist, true);

    if ( is_wp_error($tasklist_result) ) {
      var_dump_pre($tasklist_result->get_error_message());
    } else {
      $tasklist_id = $tasklist_result;
      // insert tasklist postmeta
      $owner_queryable = "|prj_" . $project_id . "|";
      add_post_meta( $tasklist_id, 'cuar_owner_queryable', $owner_queryable );

      $task_count = 0;
      $items = $order->get_items();
      foreach( $items as $key => $item ) {
        $product = $item->get_product();
        $product_name = $product->get_name();

        $new_task = array(
            'post_author' => 1,
            'post_title' => $product_name,
            'post_content' => $product_name,
            'post_type' => 'cuar_task',
            'post_status' => 'publish',
            'post_parent' => $tasklist_id
                          );
        $task_result = wp_insert_post($new_task, true);
        $task_count++;
      }

      // add task count to tasklist
      add_post_meta ( $tasklist_id, 'cuar_list_total_tasks', $task_count );
    }
  }
}

function add_invoice( $order, $project_id ) {
  $user = $order->get_user();
  $invoice_items = [];

  $items = $order->get_items();
  foreach( $items as $key => $item ) {
    $invoice_item = [
        "title" => "Item 1",
        "description" => "",
        "unit_price" => 10,
        "unit" => "",
        "quantity" => 1
                     ];

    array_push($invoice_items, $invoice_item );
  }

  $new_invoice = array(
      'post_author' => 1,
      'post_title' => $user->user_email . "-" . date('d.m.Y'),
      'post_content' => '',
      'post_type' => 'cuar_invoice',
      'post_status' => 'publish'
                       );
  // Return: (int|WP_Error) The post ID on success. The value 0 or WP_Error on failure.
  $invoice_result = wp_insert_post($new_invoice, true);

  $invoice_id = $invoice_result;
  // mark invoice as paid
  $invoice_term_result = wp_set_post_terms( $invoice_id, "Paid", "cuar_invoice_status");

  // insert postmetas
  $owner_queryable = "|prj_" . $project_id . "|";
  add_post_meta( $invoice_id, 'cuar_owner_queryable', $owner_queryable );
  add_post_meta( $invoice_id, 'cuar_items', $invoice_items );
  $billing_address = [
      "name" => "name",
      "company" => "company",
      "vat_number" => "nif",
      "logo_url" => "",
      "line1" => "street 1",
      "line 2" => "street 2",
      "zip" => "postal",
      "city" => "City",
      "country" => "ES",
      "state" => "B"
                      ];
  add_post_meta( $invoice_id, 'cuar_billing_address', $billing_address );
}

function add_project( $order ) {
  $user = $order->get_user();
  if ( $user ) {
    // get item titles of order
    $items = $order->get_items();

    // insert project into wp_posts
    // docs: https://developer.wordpress.org/reference/functions/wp_insert_post/
    $project_title = $user->user_email . "-" . date('d.m.Y');
    $new_project = array(
        'post_author' => 1,
        'post_title' => $project_title,
        'post_content' => '',
        'post_type' => 'cuar_project',
        'post_status' => 'publish'
                         );
    // Return: (int|WP_Error) The post ID on success. The value 0 or WP_Error on failure.
    $project_result = wp_insert_post($new_project, true);
    // chk for error and log
    if ( is_wp_error($project_result) ) {
      var_dump_pre($project_result->get_error_message());
    } else {
      $post_id = $project_result;

      // project status: open
      // set post terms
      // docs: https://codex.wordpress.org/Function_Reference/wp_set_post_terms
      $post_term_result = wp_set_post_terms( $post_id, "Open", "cuar_project_status");
      if( is_wp_error($post_term_result) ) {
        var_dump_pre($post_term_result);
      }

      // update project postmeta
      // docs: https://codex.wordpress.org/Function_Reference/add_post_meta
      $project_started = date('Y-m-d');
      $project_due = date('Y-m-d', strtotime($project_started . "+ 7 days")); // set due date for next week
      update_post_meta($post_id, 'cuar_project_managers', "|1|"); // set project manager to b2p
      update_post_meta($post_id, 'cuar_project_participants', "|" . $user->ID . "|"); // set current user as participant
      update_post_meta($post_id, 'cuar_project_auto_progress_enabled', 1);
      update_post_meta($post_id, 'cuar_project_started', $project_started);
      $postmeta_result = update_post_meta($post_id, 'cuar_project_due', $project_due);
      if( is_wp_error($postmeta_result) ) {
        var_dump_pre($postmeta_result->get_error_message());
      }
    }

    $project_id = $post_id;
    return $project_id;
  }
}


////////////////////////////////////////////////////////////////////////////////
// Helper
////////////////////////////////////////////////////////////////////////////////
function var_dump_pre($mixed = null) {
  // output on screen
  echo '<pre>';
  var_dump($mixed);
  echo '</pre>';

  // output in error_log
  // ob_start();
  // var_dump($mixed);
  // error_log(ob_get_clean());

  return null;
}


// FIX WPCustomerArea Bug:
// Bootstrap json is loaded twice (once from VamTam theme and once from WPCustomerArea)
// => deactivate VamTam Boostrap javascript while using WPCustomerArea
// function fix_cuar_and_theme_bootstrap_conflict(){
//   if (function_exists('cuar_is_customer_area_page')
//       && (cuar_is_customer_area_page(get_queried_object_id())
//           || cuar_is_customer_area_private_content(get_the_ID())))
//   {
//     wp_dequeue_script('bootstrap-scripts');
//   }
// }
// add_action('wp_enqueue_scripts', 'fix_cuar_and_theme_bootstrap_conflict', 20);
?>
