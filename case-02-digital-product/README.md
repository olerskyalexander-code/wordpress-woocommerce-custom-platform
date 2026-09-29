# Individual Digital Product Purchase & PDF Delivery

Sanitized implementation of an individual digital-product purchase flow built on WordPress and WooCommerce.

> Production-specific product IDs, metadata keys and project identifiers have been replaced with generic placeholders. The implementation preserves the original purchase, ownership and delivery logic.

## Problem

The project needed to sell individual digital products separately from subscriptions and provide the purchased content after successful payment.

The workflow had to:

* associate a purchase with a specific digital product;
* preserve ownership at user level;
* prevent duplicate ownership records;
* deliver the purchased content automatically;
* support both recipe-based and other digital products.

## Implementation

### 1. Purchase → Order

The purchase button passes a content identifier into the WooCommerce cart.

The identifier is then transferred from:

```text
Product Page
    ↓
Cart Item
    ↓
Order Line Item
```

This keeps the purchased content associated with the WooCommerce order rather than relying only on the product ID.

### 2. Ownership Tracking

After the order reaches `completed` status, the implementation:

* retrieves the purchased content identifier;
* checks the user's existing ownership records;
* adds the new identifier if it is not already present;
* stores the resulting ownership list in user metadata.

This ownership data is later used by the site's access-control layer.

### 3. PDF Delivery

For recipe-based products, the purchased content is rendered through the existing recipe system and converted to PDF.

The workflow is:

```text
Completed Order
      ↓
Recipe ID
      ↓
Recipe Content
      ↓
HTML Cleanup
      ↓
PDF Generation
      ↓
Completed-Order Email
      ↓
PDF Attachment
```

Generated PDFs are stored temporarily and scheduled for deletion after delivery rather than being kept permanently in the uploads directory.

### 4. Alternative Digital Products

The same purchase-to-ownership pattern is also used for digital products that do not rely on the recipe system.

A separate content identifier is passed through the cart and order and stored in user ownership metadata after successful payment.

## Architecture

```text
Purchase Button
      │
      ▼
WooCommerce Cart
      │
      │ content identifier
      ▼
Order Line Item
      │
      ▼
Completed Order
      │
      ├───────────────┐
      ▼               ▼
Ownership         PDF Delivery
      │               │
      ▼               ▼
User Metadata     Email Attachment
      │
      ▼
Content Access
```

## Technical Highlights

* PHP
* WordPress actions and filters
* WooCommerce cart and order lifecycle
* WooCommerce order item metadata
* User metadata
* Purchase ownership tracking
* Custom digital-product access logic
* HTML-to-PDF generation
* WooCommerce email attachments
* Temporary file lifecycle / scheduled cleanup
* Support for multiple digital-product types

## Production Context

The original implementation was integrated into an existing WordPress/WooCommerce digital-product platform.

The portfolio version intentionally omits production-specific identifiers and project data while preserving the main implementation pattern:

**purchase → order → ownership → access / delivery.**
