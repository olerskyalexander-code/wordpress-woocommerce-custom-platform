# Custom WooCommerce Subscription & Access System

Sanitized implementation of a custom subscription and access-control workflow built on WordPress and WooCommerce.

> Production identifiers, product IDs, role names and metadata keys have been replaced with generic placeholders. The implementation preserves the original logic and architecture.

## Problem

The project required subscription logic that was not covered by the existing WordPress/WooCommerce setup:

* multiple subscription durations and access levels;
* subscription expiration;
* transitions between subscription plans;
* upgrades based on previous purchases;
* preservation of remaining subscription time;
* automatic access changes when subscription state changes.

## Implementation

### 1. Subscription processing

Completed WooCommerce orders trigger custom subscription processing.

The system:

* maps purchased products to subscription roles and durations;
* calculates the user's current subscription state;
* preserves remaining subscription time;
* extends the expiration date after a new purchase;
* stores subscription state in user metadata;
* prevents the same order from being processed twice.

### 2. Subscription upgrades

Upgrade pricing is calculated from the customer's previous subscription purchases.

The implementation:

1. retrieves relevant completed/processing orders;
2. determines the amount of previously purchased subscription time;
3. converts that time into a credit value;
4. subtracts the credit from the new subscription price;
5. applies the resulting price to the WooCommerce cart.

This allows an upgrade to account for unused value from the existing subscription rather than treating it as a completely new purchase.

### 3. Expiration

A scheduled WordPress task checks subscription expiration.

When a subscription expires, the system:

* removes the active subscription role;
* assigns the expired/default role;
* removes active subscription metadata;
* removes the premium access state.

### 4. Content access

Subscription roles are also used by the site's block-level access-control layer.

A simplified version of the relevant access check is included in `subscription-system.php`.

The full production access-control system also supports other ownership states, which are intentionally omitted from this case.

## Architecture

```text
WooCommerce Order
       │
       ▼
Subscription Processor
       │
       ├── Product → Role / Duration
       ├── Existing Expiration
       ├── Upgrade Calculation
       └── Subscription Metadata
                  │
                  ▼
             User Role
                  │
                  ▼
          Content Access
```

Expiration runs independently through scheduled WordPress execution:

```text
WP-Cron
   │
   ▼
Check Expiration
   │
   ├── Active → keep access
   │
   └── Expired
        ├── remove subscription role
        ├── assign expired role
        └── clear subscription state
```

## Technical Highlights

* PHP
* WordPress actions and filters
* WooCommerce order lifecycle
* WooCommerce cart price manipulation
* WordPress user roles
* User/order metadata
* Subscription state management
* Custom upgrade/proration logic
* WP-Cron
* Block rendering / access control
* Duplicate order-processing protection

## Production Context

The original implementation was integrated into an existing WordPress/WooCommerce project and extended its existing role and content-access infrastructure rather than replacing it with a separate subscription plugin.

The repository contains a sanitized portfolio version for demonstrating the implementation approach without exposing production-specific identifiers or data.
