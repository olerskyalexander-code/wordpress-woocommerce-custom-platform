```php
<?php
/**
 * Individual Digital Product Purchase & PDF Delivery
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

const DIGITAL_PRODUCT_RECIPE = 20001;
const DIGITAL_PRODUCT_RECIPE_ALT = 20002;


/*
|--------------------------------------------------------------------------
| Pass digital product identifier to cart
|--------------------------------------------------------------------------
*/

/**
 * Attach the recipe/content identifier to the WooCommerce cart item.
 *
 * The identifier comes from the purchase button on the product page.
 */
add_filter(
    'woocommerce_add_cart_item_data',
    function ($cart_item_data, $product_id) {

        if (
            empty($_POST['recipe_id'])
        ) {
            return $cart_item_data;
        }

        $recipe_id = absint($_POST['recipe_id']);

        if (!$recipe_id) {
            return $cart_item_data;
        }

        $cart_item_data['recipe_id'] = $recipe_id;

        /*
         * Prevent WooCommerce from merging purchases
         * with different digital products.
         */
        $cart_item_data['unique_key'] = wp_generate_uuid4();

        return $cart_item_data;
    },
    10,
    2
);


/*
|--------------------------------------------------------------------------
| Transfer identifier from cart to order
|--------------------------------------------------------------------------
*/

add_action(
    'woocommerce_checkout_create_order_line_item',
    function ($item, $cart_item_key, $values) {

        if (empty($values['recipe_id'])) {
            return;
        }

        $item->add_meta_data(
            'recipe_id',
            absint($values['recipe_id']),
            true
        );
    },
    10,
    3
);


/*
|--------------------------------------------------------------------------
| Grant ownership after successful payment
|--------------------------------------------------------------------------
*/

add_action(
    'woocommerce_order_status_completed',
    function ($order_id) {

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();

        if (!$user_id) {
            return;
        }

        /*
         * Read existing ownership records.
         */
        $owned = get_user_meta(
            $user_id,
            'bought_recipes',
            true
        );

        $owned = $owned
            ? array_filter(
                array_map(
                    'absint',
                    explode(',', $owned)
                )
            )
            : [];

        foreach ($order->get_items() as $item) {

            $product_id = $item->get_product_id();

            /*
             * Only process the configured digital products.
             */
            if (
                $product_id !== DIGITAL_PRODUCT_RECIPE &&
                $product_id !== DIGITAL_PRODUCT_RECIPE_ALT
            ) {
                continue;
            }

            $recipe_id = absint(
                $item->get_meta('recipe_id')
            );

            if (!$recipe_id) {
                continue;
            }

            /*
             * Avoid duplicate ownership records.
             */
            if (!in_array($recipe_id, $owned, true)) {
                $owned[] = $recipe_id;
            }
        }

        /*
         * Store ownership as a simple user-level index.
         */
        if ($owned) {

            update_user_meta(
                $user_id,
                'bought_recipes',
                implode(',', $owned)
            );
        }
    }
);


/*
|--------------------------------------------------------------------------
| PDF generation and email delivery
|--------------------------------------------------------------------------
*/

/**
 * Generate a PDF from the purchased digital content
 * and attach it to the completed-order email.
 */
add_filter(
    'woocommerce_email_attachments',
    function (
        $attachments,
        $email_id,
        $order,
        $email
    ) {

        if (
            $email_id !== 'customer_completed_order' ||
            !$order instanceof WC_Order
        ) {
            return $attachments;
        }

        foreach ($order->get_items() as $item) {

            $product_id = $item->get_product_id();

            if (
                $product_id !== DIGITAL_PRODUCT_RECIPE &&
                $product_id !== DIGITAL_PRODUCT_RECIPE_ALT
            ) {
                continue;
            }

            $recipe_id = absint(
                $item->get_meta('recipe_id')
            );

            if (!$recipe_id) {
                continue;
            }

            /*
             * Retrieve the original digital content.
             *
             * The production implementation uses the
             * WP Recipe Maker shortcode.
             */
            $content = do_shortcode(
                sprintf(
                    '[wprm-recipe id="%d"]',
                    $recipe_id
                )
            );

            if (!$content) {
                continue;
            }

            /*
             * Sanitize the generated HTML before PDF conversion.
             *
             * The production implementation removes
             * interactive elements and unnecessary markup.
             */
            $content = preg_replace(
                '/<iframe.*?<\/iframe>/is',
                '',
                $content
            );

            $content = preg_replace(
                '/<video.*?<\/video>/is',
                '',
                $content
            );

            $content = preg_replace(
                '/<a[^>]*>(.*?)<\/a>/is',
                '$1',
                $content
            );

            /*
             * Dompdf is used in the production implementation.
             *
             * The dependency itself is intentionally not included
             * in this portfolio fragment.
             */
            if (
                !class_exists('\Dompdf\Dompdf')
            ) {
                continue;
            }

            $dompdf = new \Dompdf\Dompdf();

            $dompdf->setPaper('A4');

            $dompdf->loadHtml($content);

            $dompdf->render();

            $pdf = $dompdf->output();

            /*
             * Store the generated PDF temporarily.
             */
            $upload = wp_upload_dir();

            $filename = sprintf(
                'digital-product-%d-%s.pdf',
                $recipe_id,
                wp_generate_password(8, false)
            );

            $filepath = trailingslashit(
                $upload['basedir']
            ) . $filename;

            file_put_contents(
                $filepath,
                $pdf
            );

            $attachments[] = $filepath;

            /*
             * Remove the temporary file after email processing.
             *
             * The production implementation schedules cleanup
             * rather than keeping generated PDFs permanently.
             */
            wp_schedule_single_event(
                time() + 300,
                'cleanup_digital_product_pdf',
                [$filepath]
            );
        }

        return $attachments;
    },
    10,
    4
);


/*
|--------------------------------------------------------------------------
| Temporary PDF cleanup
|--------------------------------------------------------------------------
*/

add_action(
    'cleanup_digital_product_pdf',
    function ($filepath) {

        if (
            is_string($filepath) &&
            file_exists($filepath)
        ) {
            unlink($filepath);
        }
    }
);


/*
|--------------------------------------------------------------------------
| Alternative digital-product ownership
|--------------------------------------------------------------------------
*/

/**
 * Some digital products do not use the recipe system.
 *
 * The same purchase → order → ownership pattern can be
 * applied using a separate content identifier.
 */

add_filter(
    'woocommerce_add_cart_item_data',
    function ($cart_item_data) {

        if (
            empty($_POST['digital_content_id'])
        ) {
            return $cart_item_data;
        }

        $content_id = absint(
            $_POST['digital_content_id']
        );

        if (!$content_id) {
            return $cart_item_data;
        }

        $cart_item_data['digital_content_id'] = $content_id;

        $cart_item_data['unique_key'] =
            wp_generate_uuid4();

        return $cart_item_data;
    },
    20
);


add_action(
    'woocommerce_checkout_create_order_line_item',
    function ($item, $cart_item_key, $values) {

        if (
            empty($values['digital_content_id'])
        ) {
            return;
        }

        $item->add_meta_data(
            'digital_content_id',
            absint($values['digital_content_id']),
            true
        );
    },
    20,
    3
);


add_action(
    'woocommerce_order_status_completed',
    function ($order_id) {

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();

        if (!$user_id) {
            return;
        }

        $owned = get_user_meta(
            $user_id,
            'bought_digital_content',
            true
        );

        $owned = $owned
            ? array_filter(
                array_map(
                    'absint',
                    explode(',', $owned)
                )
            )
            : [];

        foreach ($order->get_items() as $item) {

            $content_id = absint(
                $item->get_meta('digital_content_id')
            );

            if (
                !$content_id ||
                in_array($content_id, $owned, true)
            ) {
                continue;
            }

            $owned[] = $content_id;
        }

        if ($owned) {

            update_user_meta(
                $user_id,
                'bought_digital_content',
                implode(',', $owned)
            );
        }
    },
    20
);
```
