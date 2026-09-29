```php
<?php
/**
 * Tag-Based Digital Product Collections & Access Control
 *
 * Sanitized portfolio version.
 *
 * Production-specific product IDs, taxonomy values and
 * metadata keys have been replaced with generic identifiers.
 */


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

const COLLECTION_PRODUCT_ID = 30001;
const COLLECTION_TAXONOMY    = 'post_tag';


/*
|--------------------------------------------------------------------------
| Collection purchase
|--------------------------------------------------------------------------
*/

/**
 * Start a collection purchase and send the user directly to checkout.
 *
 * The collection is identified by a taxonomy term.
 */
add_action(
    'template_redirect',
    function () {

        if (
            empty($_GET['collection']) ||
            empty($_GET['add-collection'])
        ) {
            return;
        }

        $collection_slug = sanitize_title(
            wp_unslash($_GET['collection'])
        );

        if (!$collection_slug) {
            return;
        }

        $term = get_term_by(
            'slug',
            $collection_slug,
            COLLECTION_TAXONOMY
        );

        if (
            !$term ||
            is_wp_error($term)
        ) {
            return;
        }

        /*
         * Collection price is configured on the taxonomy term.
         */
        $price = get_term_meta(
            $term->term_id,
            'collection_price',
            true
        );

        if ($price === '') {
            return;
        }

        /*
         * Collection purchases use a single WooCommerce
         * product as the purchase container.
         */
        WC()->cart->empty_cart();

        WC()->cart->add_to_cart(
            COLLECTION_PRODUCT_ID,
            1,
            0,
            [],
            [
                'collection_slug' => $collection_slug,
                'collection_price' => (float) $price,
                'unique_key'      => wp_generate_uuid4(),
            ]
        );

        /*
         * Skip the cart and go directly to checkout.
         */
        wp_safe_redirect(
            wc_get_checkout_url()
        );

        exit;
    }
);


/*
|--------------------------------------------------------------------------
| Collection purchase button
|--------------------------------------------------------------------------
*/

/**
 * Usage:
 *
 * [buy_collection tag="example-collection"]
 */
add_shortcode(
    'buy_collection',
    function ($atts) {

        $atts = shortcode_atts(
            [
                'tag' => '',
            ],
            $atts
        );

        $collection_slug = sanitize_title(
            $atts['tag']
        );

        if (!$collection_slug) {
            return '';
        }

        $term = get_term_by(
            'slug',
            $collection_slug,
            COLLECTION_TAXONOMY
        );

        if (
            !$term ||
            is_wp_error($term)
        ) {
            return '';
        }

        $price = get_term_meta(
            $term->term_id,
            'collection_price',
            true
        );

        if ($price === '') {
            return '';
        }

        $checkout_url = add_query_arg(
            [
                'collection'    => $collection_slug,
                'add-collection' => 1,
            ],
            home_url('/')
        );

        return sprintf(
            '<a class="collection-purchase-button" href="%s">
                Buy %s — %s
            </a>',
            esc_url($checkout_url),
            esc_html($term->name),
            esc_html(
                wc_price((float) $price)
            )
        );
    }
);


/*
|--------------------------------------------------------------------------
| Cart item data
|--------------------------------------------------------------------------
*/

add_filter(
    'woocommerce_add_cart_item_data',
    function (
        $cart_item_data,
        $product_id
    ) {

        if (
            $product_id !== COLLECTION_PRODUCT_ID ||
            empty($_POST['collection_slug'])
        ) {
            return $cart_item_data;
        }

        $slug = sanitize_title(
            wp_unslash($_POST['collection_slug'])
        );

        $cart_item_data['collection_slug'] = $slug;

        /*
         * Price is reloaded from the taxonomy term
         * rather than trusted directly from the request.
         */
        $term = get_term_by(
            'slug',
            $slug,
            COLLECTION_TAXONOMY
        );

        if (
            $term &&
            !is_wp_error($term)
        ) {

            $price = get_term_meta(
                $term->term_id,
                'collection_price',
                true
            );

            if ($price !== '') {
                $cart_item_data['collection_price'] =
                    (float) $price;
            }
        }

        $cart_item_data['unique_key'] =
            wp_generate_uuid4();

        return $cart_item_data;
    },
    10,
    2
);


/*
|-------------------------------------------------------------------------
```
