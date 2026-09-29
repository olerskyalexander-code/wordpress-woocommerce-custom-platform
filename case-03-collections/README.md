# Tag-Based Digital Product Collections & Access Control

Sanitized implementation of a reusable digital-product collection system built on WordPress and WooCommerce.

> Production-specific product IDs, taxonomy values and metadata keys have been replaced with generic placeholders. The implementation preserves the original collection, pricing, ownership and access-control flow.

## Problem

The existing system supported individual digital-product purchases, but there was no reusable way to sell groups of products as collections.

The collection system needed to:

* define collection membership through WordPress taxonomy;
* configure a separate price for each collection;
* use one WooCommerce product as the purchase container;
* track collection ownership;
* reuse the existing content-access mechanism.

## Implementation

### 1. Taxonomy-Based Collections

WordPress taxonomy terms are used to define collections.

The term itself identifies the collection, while term metadata stores its configured price.

```text
Taxonomy Term
    ├── Collection name
    ├── Collection slug
    └── Collection price
```

This allows new collections to be created without adding new product-specific PHP logic.

### 2. Dynamic WooCommerce Purchase

A single WooCommerce product acts as the purchase container.

When a collection is purchased:

1. the collection slug is passed to the purchase flow;
2. the configured taxonomy price is retrieved;
3. the collection is added to the cart;
4. the WooCommerce product price is replaced with the collection price;
5. the user is sent directly to checkout.

```text
Collection
    ↓
Taxonomy Term
    ↓
Configured Price
    ↓
WooCommerce Product
    ↓
Checkout
```

### 3. Order → Ownership

The collection identifier is stored in the WooCommerce order line item.

After the order reaches `completed` status, the collection is added to the user's ownership metadata.

```text
Completed Order
      ↓
Collection Slug
      ↓
User Metadata
      ↓
Collection Ownership
```

Duplicate ownership records are ignored.

### 4. Access Control

Collection ownership is integrated into the existing block-level content protection system.

Content blocks can be associated with a collection through a CSS-class convention. The access layer checks the current user's purchased collections before rendering the block.

This allows the same access mechanism to be reused across different collections instead of implementing separate rules for every product group.

## Architecture

```text
WordPress Taxonomy
        │
        ├── Collection
        └── Price
             │
             ▼
      WooCommerce Purchase
             │
             ▼
          Order
             │
             ▼
    Collection Ownership
             │
             ▼
       Access Control
             │
             ▼
     Protected Content
```

## Technical Highlights

* PHP
* WordPress taxonomy API
* Taxonomy term metadata
* WordPress shortcodes
* WordPress actions and filters
* WooCommerce cart manipulation
* Dynamic product pricing
* WooCommerce order item metadata
* User ownership metadata
* Block rendering / content access control
* Reusable collection configuration

## Current Status

The collection system was implemented and tested with a test user.

The portfolio implementation demonstrates the technical workflow; production-specific collection data and identifiers are intentionally omitted.

## Production Context

The system was designed as an extension of the existing digital-product infrastructure rather than as a separate collection plugin.

The main design principle was to make the taxonomy define **what belongs to a collection**, while user ownership defines **who can access it**.

This separates collection configuration from the underlying purchase and access logic.
