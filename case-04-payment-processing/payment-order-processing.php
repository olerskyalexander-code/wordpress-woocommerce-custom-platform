```php
<?php
/**
 * WooCommerce Checkout, Payment & Order Processing
 *
 * Sanitized portfolio version.
 *
 * Production-specific product IDs, metadata keys and
 * project-specific identifiers have been replaced.
 */


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

const PAYMENT_PRODUCT_ID = 40001;


/*
|--------------------------------------------------------------------------
| Specialized checkout / cart flow
|--------------------------------------------------------------------------
*/

/**
 * Start a specialized purchase flow.
 *
 * The original implementation uses a dedicated purchase URL
 * to prepare the cart before sending the customer to checkout.
 */
add_action(
    'template_redirect',
    function () {

        if (
            empty($_GET['payment_product']) ||
            empty($_GET['start-payment'])
        ) {
            return;
        }

        $product_id = absint(
            $_GET['payment_product']
        );

        if (
            !$product_id ||
            $product_id !== PAYMENT_PRODUCT_ID
        ) {
            return;
        }

        /*
         * This purchase flow is intended to contain
         * only the selected product.
         */
        WC()->cart->empty_cart();

        WC()->cart->add_to_cart(
            $product_id,
            1,
            0,
            [],
            [
                'unique_key' => wp_generate_uuid4(),
            ]
        );

        /*
         * Skip the cart and continue directly to checkout.
         */
        wp_safe_redirect(
            wc_get_checkout_url()
        );

        exit;
    }
);


/*
|--------------------------------------------------------------------------
| External payment → WooCommerce order
|--------------------------------------------------------------------------
*/

/**
 * Example AJAX entry point for an external payment flow.
 *
 * The production implementation used an external payment/subscription
 * identifier and created the corresponding WooCommerce order.
 */
add_action(
    'wp_ajax_create_external_payment_order',
    'create_external_payment_order'
);

add_action(
    'wp_ajax_nopriv_create_external_payment_order',
    'create_external_payment_order'
);


function create_external_payment_order()
{
    /*
     * The production flow required an authenticated customer.
     */
    if (!is_user_logged_in()) {

        wp_send_json_error([
            'message' => 'Authentication required.',
        ]);
    }

    $user_id = get_current_user_id();

    /*
     * External payment/subscription reference.
     */
    $external_payment_id = !empty($_POST['payment_id'])
        ? sanitize_text_field(
            wp_unslash($_POST['payment_id'])
        )
        : '';

    /*
     * Product being purchased.
     */
    $product_id = !empty($_POST['product_id'])
        ? absint($_POST['product_id'])
        : 0;

    if (
        !$external_payment_id ||
        !$product_id
    ) {

        wp_send_json_error([
            'message' => 'Invalid payment data.',
        ]);
    }

    $product = wc_get_product($product_id);

    if (!$product) {

        wp_send_json_error([
            'message' => 'Invalid product.',
        ]);
    }


    /*
     * Create WooCommerce order programmatically.
     */
    $order = wc_create_order([
        'customer_id' => $user_id,
    ]);

    if (
        is_wp_error($order)
    ) {

        wp_send_json_error([
            'message' => 'Unable to create order.',
        ]);
    }


    /*
     * Add the purchased product.
     */
    $order->add_product(
        $product,
        1
    );


    /*
     * Calculate WooCommerce totals.
     */
    $order->calculate_totals();


    /*
     * Keep the external payment*
```
