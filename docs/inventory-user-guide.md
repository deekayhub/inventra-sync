# Inventory User Guide

This guide explains how to use Inventra Sync from a daily operations point of view. It focuses on products, warehouses, stock levels, stock adjustments, the inventory ledger, and reservations. It assumes you are working inside a workspace.

The most important rule is:

> Adding a product does not add warehouse stock.

A product describes what you sell. Stock is tracked separately for each **product variant** in each **warehouse**. You create the first stock record with **Adjust stock**.

## 1. The inventory model

| Term | What it means | Where you see it |
| --- | --- | --- |
| Product | A catalog item, such as “Blue Notebook”. | **Products** |
| Variant / SKU | The sellable version of a product. Every stock movement is for a variant. | Product form, **Adjust stock**, **Stock levels** |
| Warehouse | A physical or logical place that holds stock. | **Warehouses** |
| Stock record | The quantity of one variant in one warehouse. | **Stock levels** |
| Inventory ledger | The permanent history of every stock and reservation change. | **Inventory ledger** |
| Reservation | Stock held for an order or reference before it is committed or released. | **Reservations** |

A stock record is unique to one combination:

```text
warehouse + product variant
```

That means the same SKU can have separate quantities in “Default”, “Main Store”, and “Overflow”.

## 2. Recommended workflow

Use this order when setting up new inventory:

1. Create the product and at least one variant in **Products**.
2. Check that you have a warehouse in **Warehouses**.
3. Open **Adjust stock**.
4. Choose the warehouse and the variant.
5. Enter a positive quantity to receive stock.
6. Check the result in **Stock levels**.
7. Use **Inventory ledger** when you need to understand why a quantity changed.
8. Use **Reservations** only when stock must be held for an order or reference.

## 3. Add a product

Go to **Products → New product** and fill in the product details.

Important fields:

| Field | How to use it |
| --- | --- |
| Product name | The name shown across the catalog and inventory screens. |
| Category | Optional grouping from **Categories**. |
| Type | Use `Simple` for a one-variant product, or `Variable` when you plan to add options such as size or color. |
| Status | `Draft`, `Active`, or `Archived`. |
| Low stock threshold | Catalog-level alert setting. The **Stock levels** page uses the threshold stored on the stock record. |
| Buffer stock | Catalog-level safety buffer setting. The stock record has its own buffer value used for channel-available stock. |
| Description | Optional internal description. |
| SKU | Required for every variant and unique in the workspace. Use the same SKU when adjusting stock. |
| Title | Optional variant label, for example `Small` or `Blue`. |
| Barcode | Optional barcode value. |
| Set primary | Marks the main variant for the product. |

Click **Add variant** if the product has more than one sellable version, then click **Create product**.

### There is no quantity field here

The product form intentionally does not ask for a quantity. It creates only:

- the product
- its variants/SKUs

It does **not** create a warehouse stock record and it does **not** create a ledger entry.

After saving, the product appears in **Products**, but **Warehouses** and **Stock levels** will not show stock for it yet. This is expected.

## 4. Why the warehouse stock record does not appear

This is the most common confusion.

- **Warehouses → Stock records** counts stock records that already exist. It does not count products.
- **Stock levels** lists stock records. It does not list every product variant.
- A newly added product has variants, but no stock record until a successful adjustment or reservation creates one.

If demo or seeded data shows stock immediately, it is because that data also created stock records and ledger movements. Manually creating a product does not do that.

To make stock appear, continue with **Adjust stock**.

## 5. Check or create a warehouse

Go to **Warehouses**.

New workspaces normally receive an active warehouse named **Default**. If you need another location, click **New warehouse** and fill in:

- **Warehouse name**
- **Code** — must be unique in the workspace
- **Address** — optional
- **Set as default warehouse** — optional
- **Active** — keep this checked if the warehouse should be selectable for adjustments

Only active warehouses appear in the **Adjust stock** and **New reservation** forms.

## 6. Add stock with Adjust stock

Go to **Inventory → Adjust stock**.

Fill in the form:

| Field | What to enter |
| --- | --- |
| Warehouse | The location receiving or losing stock. |
| Product variant | Shown as `Product name — SKU`, with the variant title in brackets when present. |
| Quantity change | Positive to receive stock, negative to reduce stock. Zero is not allowed. |
| Reference | Optional note such as a purchase order, batch number, or transfer ID. |
| Reason | Required explanation, such as `Opening stock` or `Damaged goods`. |

Click **Record adjustment**.

What happens:

1. Inventra Sync finds the stock record for that warehouse and variant.
2. If no record exists, it creates one starting at `0`.
3. It applies the quantity change.
4. It writes an immutable **Adjustment** entry to the inventory ledger.
5. It sends you to **Stock levels** with the message `Stock adjustment recorded.`

### Example: receive opening stock

| Step | Warehouse | Variant | Quantity change | On hand after |
| --- | --- | --- | --- | --- |
| Add product | — | `NOTE-A5 — NOTE-A5` | — | no stock record |
| Adjust stock | Default | `NOTE-A5 — NOTE-A5` | `+25` | `25` |

### Example: reduce stock

If 3 notebooks are damaged:

- Warehouse: `Default`
- Product variant: `NOTE-A5 — NOTE-A5`
- Quantity change: `-3`
- Reason: `Damaged goods`

New on-hand becomes `22`.

A negative adjustment is rejected if it would make on-hand stock less than `0`.

## 7. Read the Stock levels page

Go to **Inventory → Stock levels**.

You can filter by warehouse and search by SKU or title. The table shows:

| Column | Meaning |
| --- | --- |
| On hand | Physical quantity currently in the warehouse. |
| Reserved | Quantity held by reservations and not available to sell. |
| Available | Quantity you can use internally. |
| Channel available | Quantity safe to show to sales channels after the safety buffer. |
| Low | Badge shown when available quantity is at or below the stock record threshold. |

The calculations are:

```text
Available        = On hand - Reserved
Channel available = MAX(0, On hand - Reserved - Buffer stock)
```

Example:

| On hand | Reserved | Buffer stock | Available | Channel available |
| --- | --- | --- | --- | --- |
| 25 | 0 | 0 | 25 | 25 |
| 25 | 10 | 0 | 15 | 15 |
| 25 | 10 | 5 | 15 | 10 |

Reserved stock is still included in **On hand**. It is removed from **Available** and **Channel available**.

## 8. Use the Inventory ledger

Go to **Inventory → Inventory ledger**.

The ledger is an immutable history. You cannot edit or delete normal ledger entries from the UI. Use it to answer questions such as:

- Who changed the quantity?
- Why was stock reduced?
- Which purchase order or batch caused an adjustment?
- Was a reservation released, expired, or committed?

You can filter by:

- warehouse
- movement type
- SKU, reason, or reference

Movement types you may see:

| Type | Meaning |
| --- | --- |
| Adjustment | Manual stock received or removed. |
| Opening stock | Initial stock setup. |
| Reservation | Stock was reserved. |
| Reservation release | Reserved stock was returned to availability. |
| Commitment | Reserved stock was fulfilled and deducted from on hand. |
| Reservation expiry | Reserved stock was released after expiry. |

Each row shows the signed change, on-hand quantity after the change, reserved quantity after the change, reason, reference, and actor.

## 9. Use reservations

Reservations hold available stock before fulfilment. Go to **Inventory → Reservations → New reservation**.

Fields:

| Field | How to use it |
| --- | --- |
| Warehouse | Where the stock is held. |
| Product variant | The SKU being reserved. |
| Quantity | Must be at least `1` and no more than available stock. |
| Expires at | Optional expiry time. |
| External reference | Usually an external order ID. Reusing the same reference will not create a duplicate reservation. |

Click **Reserve stock**.

After reserving:

- **On hand** does not change.
- **Reserved** increases.
- **Available** decreases.

On the **Reservations** list, a reserved row has three actions:

| Action | Effect |
| --- | --- |
| Commit | Fulfils the reservation: on hand and reserved both decrease by the quantity. |
| Release | Cancels the reservation: reserved decreases and stock becomes available again. |
| Expire | Marks the reservation expired and returns reserved stock to availability. |

Only a `Reserved` row can be committed, released, or expired. A committed reservation cannot be released or expired because its stock has already been deducted.

## 10. CSV import and export

On the **Products** page you can use **Import CSV** and **Export CSV**.

Import:

- requires `sku` and `name`
- optionally accepts `description`, `category`, `status`, `barcode`, `title`, `variant_attributes`, `low_stock_threshold`, and `buffer_stock`
- accepts CSV files up to 10 MB
- queues the work in the background

Export also queues a CSV file and shows a download link when it is ready.

CSV import creates products and variants only. It does **not** create warehouse stock. After importing products, use **Adjust stock** for each variant you want to stock.

## 11. Troubleshooting

### I added a product but Stock levels is empty

Expected. Add stock with **Adjust stock**. Stock levels shows stock records, not catalog products.

### The Warehouse stock records count did not change

That count updates only after a stock record exists for that warehouse. The first successful adjustment creates it.

### My variant is missing from the Adjust stock dropdown

Check that:

- the product was saved successfully
- the product has at least one variant with a SKU
- you are looking in the correct workspace

### The negative adjustment failed

The adjustment would make on-hand stock negative. Use a smaller deduction or receive more stock first.

### Available is lower than I expected

Reserved stock is excluded from available stock. Check **Reservations** for reserved rows.

### Channel available is lower than available

The stock record has a buffer. Channel available keeps that safety amount back from sales channels.

### Reusing an external reference did not create another reservation

Expected. External references are idempotent so repeated order imports or retries cannot reserve stock twice.

### A warehouse is missing from the form

Only active warehouses are shown. Open **Warehouses** and make sure the location is active.

## 12. Permissions quick reference

| Permission | Lets a user do |
| --- | --- |
| `products.view` | View products and export CSV. |
| `products.create` | Create products and import CSV. |
| `products.update` | Edit products and archive them. |
| `products.delete` | Delete catalog records where delete is available. |
| `warehouses.view` | View warehouses. |
| `warehouses.manage` | Create, edit, and delete warehouses. |
| `inventory.view_ledger` | View Stock levels and Inventory ledger. |
| `inventory.adjust` | Open Adjust stock and record adjustments. |
| `inventory.manage` | View and manage reservations. |

If a menu item is missing, the user probably does not have the matching permission.

## 13. Glossary

| Term | Short meaning |
| --- | --- |
| SKU | Stock keeping unit; the unique variant code. |
| On hand | Physical stock in a warehouse. |
| Reserved | Stock held for an order or reference. |
| Available | On hand minus reserved. |
| Buffer stock | Safety quantity held back from channel availability. |
| Channel available | Stock safe to expose to connected sales channels. |
| Ledger | Append-only history of stock and reservation changes. |
| Idempotent | Safe to repeat; repeating the same action does not duplicate its effect. |
| Commit | Fulfil a reservation and deduct stock. |
| Release | Cancel a reservation and return stock to availability. |
| Expire | End an old reservation and return stock. |
