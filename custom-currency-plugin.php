<?php 
/*
Plugin Name: Custom Currency Rates By Aamir
Version: 3.0
Author: Aamir Hussain
URI: n/a
Description: This plugin outputs currency rates on the front-end and on the back-end you can set base currency and manual rates of different currencies against the base currency. Use the shortcode [currency_converter] in the page.
*/

add_shortcode('currency_converter', 'currency_converter_cb');

function currency_converter_cb(){
    ob_start();
    $base_currency = get_option('base_currency', 'GBP');
    $saved_currencies = get_option('custom_currency_names', []);
    $saved_currency_values = get_option('custom_currency_values', []);
    $saved_currencies_with_values = [];

    // Combine currency names and values into an associative array
    foreach ($saved_currencies as $currency) {
        $currency_key = str_replace(' ', '_', $currency);
        if (isset($saved_currency_values[$currency])) {
            $saved_currencies_with_values[$currency] = $saved_currency_values[$currency];
        }
    }
    ?>
    <style>
        div#list-currencies {
            width: 49%;
            float: left;
        }
        div#the-currencies-sc h4 {
            padding: 0 !important; 
            display: inline;
            line-height: 4rem; 
        }
        form#converter_form #left, form#converter_form #right {
            float: left;
            margin-right: 15px;
            max-width: 45%;
            overflow: hidden;
        }
        form#converter_form {
            display: block;
            overflow: hidden;
            position: relative;
        }
        form#converter_form #left select, form#converter_form #right  select {   
            margin-bottom: 15px;
        }
        form label {
            margin: 10px 0;
            line-height: 30px;
        }
        form#converter_form input, form#converter_form select {
            border: 1px solid #CCC;
            overflow: hidden;
            max-width: 75%;
            border-radius: 4px;
            padding: 4px;
        }
        @media only screen and (max-width: 600px) {
            div#the-currencies-sc > #list-currencies, div#the-currencies-sc > #currency-calculator {
                clear: both;
                position: relative;
                display: block;
                padding-top: 20px;
            }
        }
    </style>

    <div id="the-currencies-sc"> 
        <div id="list-currencies"> 
            <h4>1 <?php echo esc_html($base_currency); ?>: </h4>
            <?php if(!empty($saved_currencies_with_values)): ?>
            <ul>
                <?php foreach ($saved_currencies_with_values as $currency => $value) : ?>
                    <li>
                        <label><?php echo esc_html($currency); ?></label>
                        <strong><?php echo esc_html($value); ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p>No Currency data yet.</p>
            <?php endif; ?>
        </div>    
        <div id="currency-calculator">
            <h4>Select Your Desired Currency</h4>
            <form id="converter_form">
                <div id="left">
                    <label>From: 
                    <select id="from">
                        <option value="<?php echo esc_html($base_currency); ?>" readonly><?php echo esc_html($base_currency); ?></option>
                    </select>
                    <input type="number" id="from_rate" value="1"/>
                    </label>
                </div>
                <div id="right">
                    <label>To: 
                    <select id="to">
                        <option value=""> - SELECT - </option>
                        <?php foreach ($saved_currencies as $currency) : ?>
                            <option value="<?php echo esc_attr($currency); ?>"><?php echo esc_html($currency); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" id="to_rate" readonly>
                    </label>
                </div>    
            </form>
        </div>  
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var currencyValues = <?php echo json_encode($saved_currency_values); ?>;
            var fromRateInput = document.getElementById('from_rate');
            var toRateInput = document.getElementById('to_rate');
            var toSelect = document.getElementById('to');

            function calculateToRate() {
                var fromRate = parseFloat(fromRateInput.value);
                var toCurrency = toSelect.value;
                var toRate = fromRate * (currencyValues[toCurrency] || 1);
                toRateInput.value = toRate.toFixed(2);
            }

            fromRateInput.addEventListener('input', calculateToRate);
            toSelect.addEventListener('change', calculateToRate);
        });
        </script>
    </div>    
    <?php
    $html = ob_get_clean();
    return $html;
}

// Hook to add the custom menu item
add_action('admin_menu', 'my_custom_admin_menu');

// Function to add the custom menu item
function my_custom_admin_menu() {
    // Ensure the user has the right capability
    if (function_exists('add_menu_page')) {
        add_menu_page(
            'Set Currencies',   // Page title
            'Set Currencies',   // Menu title
            'manage_options',   // Capability
            'set-currencies',   // Menu slug
            'set_currencies',   // Function to display the page content
            '',                 // Icon URL (optional)
            20                  // Position in the menu (optional)
        );
    }
}

// Function to display the content of the custom admin page
function set_currencies() {
    // Check if the form is submitted to add a new currency
    if (isset($_POST['submit_currency_name'])) {
        // Verify nonce for security
        if (check_admin_referer('save_currency_name', 'save_currency_name_nonce')) {
            // Sanitize and save the input
            $currency_name = sanitize_text_field($_POST['currency_name']);
            $currencies = get_option('custom_currency_names', []);
            if (!in_array($currency_name, $currencies)) {
                $currencies[] = $currency_name;
                update_option('custom_currency_names', $currencies);
                echo '<div class="updated"><p>Currency name saved.</p></div>';
            } else {
                echo '<div class="error"><p>Currency name already exists.</p></div>';
            }
        }
    }

    // Check if the form is submitted to update currency values
    if (isset($_POST['submit_currency_values'])) {
        // Verify nonce for security
        if (check_admin_referer('save_currency_values', 'save_currency_values_nonce')) {
            // Sanitize and save each currency value
            $currencies = get_option('custom_currency_names', []);
            $currency_values = [];
            foreach ($currencies as $currency) {
                $currency_key = str_replace(' ', '_', $currency);
                if (isset($_POST['currency_value_' . $currency_key])) {
                    $currency_values[$currency] = sanitize_text_field($_POST['currency_value_' . $currency_key]);
                }
            }
            update_option('custom_currency_values', $currency_values);
            echo '<div class="updated"><p>Currency values saved.</p></div>';
        }
    }

    // Check if a currency deletion request is made
    if (isset($_POST['delete_currency'])) {
        // Verify nonce for security
        if (check_admin_referer('delete_currency', 'delete_currency_nonce')) {
            // Sanitize the currency name
            $currency_to_delete = sanitize_text_field($_POST['delete_currency']);
            // Retrieve the existing currencies
            $currencies = get_option('custom_currency_names', []);
            // Remove the specified currency from the array
            $index = array_search($currency_to_delete, $currencies);
            if ($index !== false) {
                unset($currencies[$index]);
                // Update the currency list in the database
                update_option('custom_currency_names', $currencies);
                // Retrieve and remove the corresponding value
                $currency_values = get_option('custom_currency_values', []);
                unset($currency_values[$currency_to_delete]);
                update_option('custom_currency_values', $currency_values);
                echo '<div class="updated"><p>Currency deleted successfully.</p></div>';
            }
        }
    }

    // Check if the form is submitted to update base currency
    if (isset($_POST['submit_base_currency'])) {
        // Verify nonce for security
        if (check_admin_referer('save_base_currency', 'save_base_currency_nonce')) {
            // Sanitize and save the base currency
            $base_currency = sanitize_text_field($_POST['base_currency']);
            update_option('base_currency', $base_currency);
            echo '<div class="updated"><p>Base currency saved.</p></div>';
        }
    }

    $saved_currencies = get_option('custom_currency_names', []);
    $saved_currency_values = get_option('custom_currency_values', []);
    $base_currency = get_option('base_currency', 'GBP');
    ?>
    <div class="wrap">

    <h2>Add Base Currency</h2>
        <form method="post" action="">
            <?php wp_nonce_field('save_base_currency', 'save_base_currency_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="base_currency">Base Currency</label></th>
                    <td> 
                             
                                <input id="base_currency" name="base_currency" class="regular-text" type="text" value="<?php echo $base_currency ; ?>">
                            
                         
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Base Currency', 'primary', 'submit_base_currency'); ?>
        </form>
    </div>

        <h2>Add secondary Currency</h2>
        <form method="post" action="">
            <?php wp_nonce_field('save_currency_name', 'save_currency_name_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="currency_name">Currency Name</label></th>
                    <td><input type="text" id="currency_name" name="currency_name" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button('Save Currency Name', 'primary', 'submit_currency_name'); ?>
        </form>

        <h2>Set Values to Currencies</h2>
        <div>
            <?php if (!empty($saved_currencies)) : ?>
                <form method="post" action="">
                    <?php wp_nonce_field('save_currency_values', 'save_currency_values_nonce'); ?>
                    <?php wp_nonce_field('delete_currency', 'delete_currency_nonce'); ?>
                    <table class="form-table">
                        <?php foreach ($saved_currencies as $currency) : ?>
                            <?php $currency_key = str_replace(' ', '_', $currency); ?>
                            <tr valign="top">
                                <th scope="row"><?php echo esc_html($currency); ?></th>
                                <td>
                                    <input type="text" id="currency_value_<?php echo esc_attr($currency_key); ?>" name="currency_value_<?php echo esc_attr($currency_key); ?>" value="<?php echo esc_attr($saved_currency_values[$currency] ?? ''); ?>" class="regular-text" />
                                    <button type="submit" class="button" name="delete_currency" value="<?php echo esc_attr($currency); ?>" onclick="return confirm('Are you sure you want to delete this currency?');">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php submit_button('Save Currency Values', 'primary', 'submit_currency_values'); ?>
                </form>
            <?php else : ?>
                <p>No currencies saved yet.</p>
            <?php endif; ?>
        </div>


    <?php
}
?>
