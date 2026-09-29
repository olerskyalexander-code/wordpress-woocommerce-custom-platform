```php
<?php
/**
 * Custom WooCommerce Subscription & Access System
 *
 * Sanitized portfolio version.
 * Production-specific product IDs, role names and metadata keys
 * have been replaced with generic identifiers.
 */

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

const SUB_PRODUCT_MONTHLY_LEGACY = 10001;
const SUB_PRODUCT_ANNUAL_LEGACY  = 10002;
const SUB_PRODUCT_MONTHLY        = 10003;
const SUB_PRODUCT_ANNUAL         = 10004;
const SUB_PRODUCT_THREE_MONTH    = 10005;

const SUB_ROLE_MONTHLY_LEGACY = 'role_monthly_legacy';
const SUB_ROLE_ANNUAL_LEGACY  = 'role_annual_legacy';
const SUB_ROLE_MONTHLY        = 'role_monthly';
const SUB_ROLE_ANNUAL         = 'role_annual';
const SUB_ROLE_THREE_MONTH    = 'role_three_month';
const SUB_ROLE_EXPIRED        = 'role_expired';


/*
|--------------------------------------------------------------------------
| Subscription purchase history
|--------------------------------------------------------------------------
*/

/**
 * Count monthly subscription purchases after the latest annual purchase.
 */
function count_monthly_subscription_purchases($user_id)
{
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status'      => ['completed', 'processing'],
        'limit'       => -1,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);

    $latest_annual_date = null;
    $monthly_count      = 0;

    foreach ($orders as $order) {

        foreach ($order->get_items() as $item) {

            $product_id = $item->get_product_id();

            if (
                $product_id === SUB_PRODUCT_ANNUAL_LEGACY ||
                $product_id === SUB_PRODUCT_ANNUAL
            ) {
                $latest_annual_date = $order->get_date_created();
                break 2;
            }
        }
    }

    foreach ($orders as $order) {

        $order_date = $order->get_date_created();

        if (
            $latest_annual_date &&
            $order_date &&
            $order_date <= $latest_annual_date
        ) {
            continue;
        }

        foreach ($order->get_items() as $item) {

            if ($item->get_product_id() === SUB_PRODUCT_MONTHLY_LEGACY) {
                $monthly_count++;
            }
        }
    }

    return $monthly_count;
}


/**
 * Calculate subscription months purchased through the newer plans.
 *
 * Monthly plan  = 1 month
 * Three-month plan = 3 months
 */
function count_subscription_months($user_id)
{
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status'      => ['completed', 'processing'],
        'limit'       => -1,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);

    $latest_annual_date = null;
    $months             = 0;

    foreach ($orders as $order) {

        foreach ($order->get_items() as $item) {

            if ($item->get_product_id() === SUB_PRODUCT_ANNUAL) {
                $latest_annual_date = $order->get_date_created();
                break 2;
            }
        }
    }

    foreach ($orders as $order) {

        $order_date = $order->get_date_created();

        if (
            $latest_annual_date &&
            $order_date &&
            $order_date <= $latest_annual_date
        ) {
            continue;
        }

        foreach ($order->get_items() as $item) {

            switch ($item->get_product_id()) {

                case SUB_PRODUCT_MONTHLY:
                    $months += 1;
                    break;

                case SUB_PRODUCT_THREE_MONTH:
                    $months += 3;
                    break;
            }
        }
    }

    return $months;
}


/*
|--------------------------------------------------------------------------
| Upgrade flags
|--------------------------------------------------------------------------
*/

add_filter(
    'woocommerce_add_cart_item_data',
    function ($cart_item_data) {

        if (!empty($_POST['subscription_upgrade'])) {
            $cart_item_data['subscription_upgrade'] =
                sanitize_text_field($_POST['subscription_upgrade']);
        }

        return $cart_item_data;
    },
    10,
    1
);


/*
|--------------------------------------------------------------------------
| Dynamic upgrade pricing
|--------------------------------------------------------------------------
*/

add_action(
    'woocommerce_before_calculate_totals',
    function ($cart) {

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {

            if (empty($cart_item['subscription_upgrade'])) {
                continue;
            }

            $product_id = $cart_item['product_id'];

            if (
                $product_id !== SUB_PRODUCT_ANNUAL_LEGACY &&
                $product_id !== SUB_PRODUCT_ANNUAL
            ) {
                continue;
            }

            $user_id = get_current_user_id();

            if (!$user_id) {
                continue;
            }

            $months = (
                $product_id === SUB_PRODUCT_ANNUAL_LEGACY
            )
                ? count_monthly_subscription_purchases($user_id)
                : count_subscription_months($user_id);

            /*
             * Upgrade credit is limited to the configured maximum.
             */
            $months = min($months, 8);

            $product = $cart_item['data'];

            $annual_price = (float) $product->get_regular_price();

            if ($annual_price <= 0) {
                continue;
            }

            $monthly_value = $annual_price / 12;
            $upgrade_price = max(
                0,
                $annual_price - ($months * $monthly_value)
            );

            $product->set_price($upgrade_price);
        }
    }
);


/*
|--------------------------------------------------------------------------
| Subscription processing
|--------------------------------------------------------------------------
*/

add_action(
    'woocommerce_order_status_completed',
    'process_custom_subscription',
    10,
    1
);

function process_custom_subscription($order_id)
{
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    /*
     * Prevent duplicate processing of the same order.
     */
    if ($order->get_meta('_subscription_processed')) {
        return;
    }

    $user_id = $order->get_user_id();

    if (!$user_id) {
        return;
    }

    $user = get_user_by('id', $user_id);

    if (!$user) {
        return;
    }

    $current_expiration = (int) get_user_meta(
        $user_id,
        'subscription_expire_date',
        true
    );

    $current_role = get_user_meta(
        $user_id,
        'assigned_subscription_role',
        true
    );

    $now = current_time('timestamp');

    /*
     * Preserve only currently active remaining time.
     */
    $remaining_days = 0;

    if ($current_expiration > $now) {
        $remaining_days = ceil(
            ($current_expiration - $now) / DAY_IN_SECONDS
        );
    }

    $role             = null;
    $duration_days    = 0;
    $is_upgrade       = false;
    $subscription_key = null;

    foreach ($order->get_items() as $item) {

        $product_id = $item->get_product_id();

        switch ($product_id) {

            case SUB_PRODUCT_MONTHLY_LEGACY:

                $role             = SUB_ROLE_MONTHLY_LEGACY;
                $duration_days    = 30;
                $subscription_key = 'monthly_legacy';

                break;

            case SUB_PRODUCT_ANNUAL_LEGACY:

                $role             = SUB_ROLE_ANNUAL_LEGACY;
                $duration_days    = 365;
                $subscription_key = 'annual_legacy';

                if (
                    $item->get_meta('subscription_upgrade')
                ) {
                    $is_upgrade = true;
                }

                break;

            case SUB_PRODUCT_MONTHLY:

                $role             = SUB_ROLE_MONTHLY;
                $duration_days    = 30;
                $subscription_key = 'monthly';

                break;

            case SUB_PRODUCT_ANNUAL:

                $role             = SUB_ROLE_ANNUAL;
                $duration_days    = 365;
                $subscription_key = 'annual';

                if (
                    $item->get_meta('subscription_upgrade')
                ) {
                    $is_upgrade = true;
                }

                break;

            case SUB_PRODUCT_THREE_MONTH:

                $role             = SUB_ROLE_THREE_MONTH;
                $duration_days    = 90;
                $subscription_key = 'three_month';

                break;
        }

        if ($role) {
            break;
        }
    }

    if (!$role) {
        return;
    }

    /*
     * Upgrade duration can be adjusted according to
     * the amount of previously purchased subscription time.
     */
    if ($is_upgrade) {

        $previous_months = (
            $subscription_key === 'annual_legacy'
        )
            ? count_monthly_subscription_purchases($user_id)
            : count_subscription_months($user_id);

        $previous_months = min($previous_months, 8);

        $duration_days -= $previous_months * 30;

        $duration_days = max($duration_days, 0);
    }

    /*
     * Keep remaining active time when extending a subscription.
     */
    $new_expiration = $now
        + (($remaining_days + $duration_days) * DAY_IN_SECONDS);

    /*
     * Subscription role transition.
     */
    $user->set_role($role);

    do_action(
        'custom_subscription_role_assigned',
        $user_id,
        $role,
        $order_id
    );

    /*
     * Store subscription state.
     */
    update_user_meta(
        $user_id,
        'subscription_expire_date',
        $new_expiration
    );

    update_user_meta(
        $user_id,
        'assigned_subscription_role',
        $role
    );

    update_user_meta(
        $user_id,
        'subscription_duration',
        $duration_days
    );

    update_user_meta(
        $user_id,
        'subscription_expiry',
        date(
            'Y-m-d H:i:s',
            $new_expiration
        )
    );

    /*
     * Example premium threshold based on accumulated
     * monthly subscription time.
     */
    if (
        $subscription_key === 'monthly' ||
        $subscription_key === 'three_month'
    ) {

        $total_months = count_subscription_months($user_id);

        if ($total_months >= 9) {
            update_user_meta(
                $user_id,
                'premium_status',
                'active'
            );
        }
    }

    /*
     * Mark order as processed.
     */
    $order->update_meta_data(
        '_subscription_processed',
        time()
    );

    $order->save();
}


/*
|--------------------------------------------------------------------------
| Automatic expiration
|--------------------------------------------------------------------------
*/

add_action(
    'init',
    function () {

        if (!wp_next_scheduled('check_custom_subscription_expiry')) {

            wp_schedule_event(
                strtotime('tomorrow midnight'),
                'daily',
                'check_custom_subscription_expiry'
            );
        }
    }
);


add_action(
    'check_custom_subscription_expiry',
    function () {

        $now = current_time('timestamp');

        $users = get_users([
            'meta_key' => 'subscription_expire_date',
            'compare'  => 'EXISTS',
        ]);

        foreach ($users as $user) {

            $expiration = (int) get_user_meta(
                $user->ID,
                'subscription_expire_date',
                true
            );

            $assigned_role = get_user_meta(
                $user->ID,
                'assigned_subscription_role',
                true
            );

            if (
                !$assigned_role ||
                !$expiration ||
                $expiration >= $now
            ) {
                continue;
            }

            /*
             * Remove active subscription access.
             */
            $user->remove_role($assigned_role);

            /*
             * Assign expired/default state.
             */
            $user->add_role(SUB_ROLE_EXPIRED);

            /*
             * Clear active subscription state.
             */
            delete_user_meta(
                $user->ID,
                'premium_status'
            );

            delete_user_meta(
                $user->ID,
                'subscription_expire_date'
            );

            delete_user_meta(
                $user->ID,
                'assigned_subscription_role'
            );

            delete_user_meta(
                $user->ID,
                'subscription_duration'
            );
        }
    }
);


/*
|--------------------------------------------------------------------------
| Subscription-based content access
|--------------------------------------------------------------------------
*/

/**
 * Simplified fragment of the production block-rendering access layer.
 *
 * The original system supports several ownership mechanisms
 * (subscriptions, individual purchases and collections).
 * This portfolio case keeps only the subscription-related part.
 */

function has_active_subscription_access($user)
{
    if (!$user || !$user->exists()) {
        return false;
    }

    $subscription_roles = [
        SUB_ROLE_MONTHLY_LEGACY,
        SUB_ROLE_ANNUAL_LEGACY,
        SUB_ROLE_MONTHLY,
        SUB_ROLE_ANNUAL,
        SUB_ROLE_THREE_MONTH,
    ];

    foreach ($subscription_roles as $role) {

        if (in_array($role, (array) $user->roles, true)) {
            return true;
        }
    }

    return false;
}


add_filter(
    'render_block',
    function ($block_content, $block) {

        if (empty($block['attrs']['className'])) {
            return $block_content;
        }

        $classes = $block['attrs']['className'];

        /*
         * Blocks marked as subscription-content are available
         * only to users with an active subscription role.
         */
        if (
            strpos($classes, 'subscription-content') !== false
        ) {

            $user = wp_get_current_user();

            if (!has_active_subscription_access($user)) {
                return '';
            }
        }

        return $block_content;
    },
    10,
    2
);
```
