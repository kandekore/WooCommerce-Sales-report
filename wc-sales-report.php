<?php
/*
Plugin Name: WooCommerce Monthly Sales Report
Description: A plugin to print off monthly sales including customer name, product, and payment details.
Version: 1.0
Author: D.Kandekore
*/

// Function to add an admin page for the sales report.
function wcmr_add_admin_page() {
    add_menu_page(
        'Monthly Sales Report', 
        'Monthly Sales Report', 
        'manage_options', 
        'wcmr-sales-report', 
        'wcmr_display_report'
    );
}
add_action('admin_menu', 'wcmr_add_admin_page');

function wcmr_get_sales_data($month = null, $year = null, $product_id = null, $customer_id = null) {
    $args = array(
        'post_type' => 'shop_order',
        'post_status' => 'wc-completed',
        'posts_per_page' => -1,
        'fields' => 'ids',
    );
    
    if ($year) {
        $args['year'] = $year;
    }

    if ($month) {
        $args['monthnum'] = $month;
    }

    $query = new WP_Query($args);
    return $query->posts;
}


function wcmr_display_report() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if the form is submitted and nonce is valid.
    if (isset($_POST['wcmr_nonce']) && wp_verify_nonce($_POST['wcmr_nonce'], 'wcmr_filter')) {
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : null;
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : null;
        $orders = wcmr_get_sales_data($month, $year);
    } else {
        $orders = wcmr_get_sales_data();
    }

       // HTML form to filter reports by month and year.
    echo '<form method="post" action="">';
    wp_nonce_field('wcmr_filter', 'wcmr_nonce');
    echo 'Month: <select name="month">
            <option value="">Select Month</option>
            <option value="01">January</option>
            <option value="02">February</option>
            <option value="03">March</option>
            <option value="04">April</option>
            <option value="05">May</option>
            <option value="06">June</option>
            <option value="07">July</option>
            <option value="08">August</option>
            <option value="09">September</option>
            <option value="10">October</option>
            <option value="11">November</option>
            <option value="12">December</option>
          </select>';
    echo ' Year: <select name="year">
            <option value="">Select Year</option>';
    $currentYear = date('Y');
    for ($i = $currentYear; $i >= $currentYear - 5; $i--) {
        echo "<option value='$i'>$i</option>";
    }
    echo '</select>';
    echo ' <input type="submit" value="Filter" />';
    echo '</form>';


    // Display the report.
    echo "<table id='reportTable' class='wp-list-table widefat fixed striped table-view-list'>";
    echo "<thead><tr>
            <th class='manage-column'>Order ID</th>
            <th class='manage-column'>Date</th>
            <th class='manage-column'>Customer Name</th>
            <th class='manage-column'>Product</th>
            <th class='manage-column'>Payment Details</th>
            <th class='manage-column'>Order Total</th>
            <th class='manage-column'>Shipping Costs</th>
            <th class='manage-column'>Fees</th> 

          </tr></thead>";
    echo "<tbody>";

    $grand_order_total = 0;
    $grand_shipping_total = 0;
       $grand_fees_total = 0; 

    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        
        foreach ($items as $item) {
            echo "<tr>";
            echo "<td><a href='" . admin_url('post.php?post=' . absint($order_id) . '&action=edit') . "'>{$order_id}</a></td>";
            echo "<td>" . date_i18n(get_option('date_format'), strtotime($order->get_date_created())) . "</td>";
            echo "<td>{$order->get_billing_first_name()} {$order->get_billing_last_name()}</td>";
            echo "<td>{$item->get_name()}</td>";
            echo "<td>{$order->get_payment_method_title()}</td>";
                 // Add order total and shipping costs to grand totals.
            $grand_order_total += $order->get_total();
            $grand_shipping_total += $order->get_shipping_total();
            
              // Fetch and display fees (assumes metadata keys are _stripe_fee and _paypal_fee).
            $stripe_fee = get_post_meta($order_id, '_stripe_fee', true);
            $paypal_fee = get_post_meta($order_id, 'PayPal Transaction Fee', true);
            $fees = $stripe_fee + $paypal_fee;
            $grand_fees_total += $fees; // Add to grand total fees

            // Display order total and shipping costs for each order.
            echo "<td>" . wc_price($order->get_total()) . "</td>";
            echo "<td>" . wc_price($order->get_shipping_total()) . "</td>";
              echo "<td>" . wc_price($fees) . "</td>";
            echo "</tr>";
        }
    }

      // Display grand totals.
    echo "<tr><td colspan='5'>Grand Total</td>";
    echo "<td>" . wc_price($grand_order_total) . "</td>";
    echo "<td>" . wc_price($grand_shipping_total) . "</td>";
     echo "<td>" . wc_price($grand_fees_total) . "</td>";
    echo "</tr>";

    echo "</table>";

    // Add print button.
    echo "<button class='button' onclick='printReport();'>Print Report</button>";
     // JavaScript function to print the report.
    echo "<script>
        function printReport() {
            var printContents = document.getElementById('reportTable').outerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
        }
    </script>";

    // Print-specific CSS to ensure only the table is printed.
    echo "<style>
        @media print {
            body * {
                visibility: hidden;
            }
            #reportTable, #reportTable * {
                visibility: visible;
            }
            #reportTable {
                position: absolute;
                left: 0;
                top: 0;
            }
        }
    </style>";
}