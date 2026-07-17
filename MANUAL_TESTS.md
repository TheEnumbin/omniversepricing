# OmniversePricing - Smart Sync Manual Test Cases

## Overview

This document provides comprehensive manual test cases for the **Smart Sync** feature (w_cron mode). The smart sync system flags products for background processing instead of immediately syncing, optimizing performance by batching operations.

## Prerequisites

- PrestaShop 1.7+ installation
- OmniversePricing module installed and enabled
- Module configured with `HISTORY_FUNC` set to "w_cron" (Smart Sync mode)
- Database access to verify `sync_status` field values

## Configuration

**Navigate to:** Back Office → Modules → Module Manager → OmniversePricing → Configure

**Required Settings:**
- **History Function:** `w_cron` (Smart Sync mode)
- **Sync Price Type:** `current` or `old_price`
- **Cron URL:** Generated automatically (use for testing sync execution)

---

## Test Suite 1: Smart Sync Flagging

### Test 1.1: Product Update Flags Product for Sync
**Purpose:** Verify that updating a product flags it for sync

**Steps:**
1. Go to Back Office → Catalog → Products
2. Select any product
3. Change the price (or any product attribute)
4. Click "Save"
5. Check the database: `SELECT product_id, sync_status FROM ps_omniversepricing_products WHERE product_id = [ID] ORDER BY date DESC LIMIT 1`

**Expected Result:**
- New record inserted with `sync_status = 'pending'`
- `last_sync_date` should be NULL

---

### Test 1.2: Combination Product Update Flags for Sync
**Purpose:** Verify that product combinations are flagged correctly

**Steps:**
1. Go to Back Office → Catalog → Products
2. Select a product with combinations (variants)
3. Go to "Combinations" tab
4. Modify any combination (price, attributes, etc.)
5. Click "Save"
6. Check database for the specific combination

**Expected Result:**
- Record with `id_product_attribute` set has `sync_status = 'pending'`

---

### Test 1.3: Bulk Product Update
**Purpose:** Verify bulk updates flag multiple products

**Steps:**
1. Go to Back Office → Catalog → Products
2. Select multiple products (checkboxes)
3. Use bulk edit to change prices
4. Save changes
5. Check database: `SELECT COUNT(*) FROM ps_omniversepricing_products WHERE sync_status = 'pending'`

**Expected Result:**
- All updated products have `sync_status = 'pending'`

---

## Test Suite 2: Smart Sync Execution

### Test 2.1: Manual Sync via Admin Button
**Purpose:** Verify the "Sync Now" button processes pending products

**Steps:**
1. Ensure you have products with `sync_status = 'pending'`
2. Go to Back Office → Modules → OmniversePricing → Configure
3. Scroll to "Sync Products Now" section
4. Click "Sync Now!!!"
5. Wait for completion (check browser console for response)
6. Check database: `SELECT sync_status, COUNT(*) FROM ps_omniversepricing_products GROUP BY sync_status`

**Expected Result:**
- Products that were 'pending' now have `sync_status = 'synced'`
- `last_sync_date` is set to current date

---

### Test 2.2: Cron URL Execution (Direct)
**Purpose:** Verify sync works via cron URL

**Steps:**
1. Copy the Cron URL from module configuration
2. Paste in browser: `[CRON_URL]?price_type=current`
3. Execute the URL
4. Check database for status changes

**Expected Result:**
- Batch of products (up to 500) processed
- Status changes from 'pending' to 'synced'

---

### Test 2.3: Batch Processing Verification
**Purpose:** Verify that sync processes products in batches

**Steps:**
1. Create 1000+ products with `sync_status = 'pending'`
   ```sql
   UPDATE ps_omniversepricing_products SET sync_status = 'pending' WHERE shop_id = 1 LIMIT 1000
   ```
2. Execute sync URL
3. Check how many were processed: `SELECT COUNT(*) FROM ps_omniversepricing_products WHERE sync_status = 'synced' AND last_sync_date = CURDATE()`
4. Re-execute sync URL multiple times

**Expected Result:**
- First run: ~500 products synced (batch size)
- Second run: Remaining ~500 synced
- Third run: No more pending products

---

### Test 2.4: Execution Time Limit
**Purpose:** Verify sync respects time limits (50 seconds)

**Steps:**
1. Create a large backlog of pending products (5000+)
2. Execute sync URL
3. Measure execution time (check response time)
4. Check remaining pending products

**Expected Result:**
- Sync stops after ~50 seconds
- Not all products processed in single run
- Remaining products still 'pending'

---

## Test Suite 3: CPU Safeguard

### Test 3.1: High Server Load Skip
**Purpose:** Verify sync skips when server load is high

**Steps:**
1. Check server load: Run `cat /proc/loadavg` in terminal
2. If load < 5.0, artificially increase load (stress tool or heavy process)
3. Execute sync URL
4. Check if sync was skipped

**Expected Result:**
- When load > 5.0, sync exits immediately
- No products processed
- No error response (silent exit)

---

### Test 3.2: Normal Load Processing
**Purpose:** Verify sync works under normal load

**Steps:**
1. Ensure server load < 5.0
2. Execute sync URL with pending products
3. Verify products are processed

**Expected Result:**
- Sync runs normally
- Products processed as expected

---

## Test Suite 4: Price Type Sync

### Test 4.1: Current Price Sync
**Purpose:** Verify 'current' price type syncs current prices

**Steps:**
1. Set `OMNIVERSEPRICING_SYNC_PRICE_TYPE` to 'current' in config
2. Flag some products for sync
3. Execute sync: `[CRON_URL]?price_type=current`
4. Check product prices in database: `SELECT product_id, price FROM ps_omniversepricing_products WHERE sync_status = 'synced' AND date = CURDATE()`
5. Compare with product catalog prices

**Expected Result:**
- Synced prices match current catalog prices

---

### Test 4.2: Old Price Sync
**Purpose:** Verify 'old_price' type syncs historical prices

**Steps:**
1. Set price_type to 'old_price' in config
2. Execute sync: `[CRON_URL]?price_type=old_price`
3. Check synced prices

**Expected Result:**
- Synced prices reflect old/historical pricing data

---

## Test Suite 5: Multi-Store Support

### Test 5.1: Shop-Specific Sync
**Purpose:** Verify sync respects shop context

**Steps:**
1. Enable multi-store mode
2. Create products in Shop A
3. Switch to Shop B context
4. Execute sync for Shop B
5. Check database: `SELECT COUNT(*) FROM ps_omniversepricing_products WHERE shop_id = [Shop B] AND sync_status = 'synced'`

**Expected Result:**
- Only products for Shop B are processed
- Shop A products unaffected

---

### Test 5.2: Cron URL per Shop
**Purpose:** Verify each shop has unique cron URL

**Steps:**
1. Navigate to Shop A context
2. Copy Cron URL
3. Navigate to Shop B context
4. Copy Cron URL
5. Compare both URLs

**Expected Result:**
- Each shop has different cron URL (different shop_id parameter)

---

## Test Suite 6: Database Integrity

### Test 6.1: No Duplicate Entries
**Purpose:** Verify sync doesn't create duplicates

**Steps:**
1. Sync a product twice (flag → sync → flag → sync)
2. Check database: `SELECT COUNT(*) FROM ps_omniversepricing_products WHERE product_id = [ID] AND date = CURDATE()`
3. Verify only one record per language/shop/currency combination

**Expected Result:**
- No duplicate entries
- One record per unique combination

---

### Test 6.2: Orphaned Data Prevention
**Purpose:** Verify no orphaned sync records

**Steps:**
1. Delete a product from catalog
2. Check database for remaining entries: `SELECT * FROM ps_omniversepricing_products WHERE product_id = [DELETED ID]`
3. Execute sync
4. Verify old entries are handled correctly

**Expected Result:**
- Orphaned entries should be cleaned or marked appropriately

---

## Test Suite 7: Error Handling

### Test 7.1: Invalid Price Type Parameter
**Purpose:** Verify invalid price_type is handled gracefully

**Steps:**
1. Execute sync with invalid price_type: `[CRON_URL]?price_type=invalid`
2. Check response

**Expected Result:**
- Falls back to 'current' price type
- No error shown
- Sync proceeds normally

---

### Test 7.2: Database Connection Failure
**Purpose:** Verify behavior when DB is unavailable

**Steps:**
1. Stop MySQL service
2. Execute sync URL
3. Start MySQL service
4. Check if any corruption occurred

**Expected Result:**
- Graceful failure
- No partial updates
- Products remain 'pending'

---

## Test Suite 8: Performance

### Test 8.1: Large Dataset Performance
**Purpose:** Verify sync performance with large product catalog

**Steps:**
1. Have 10,000+ products in catalog
2. Flag all for sync
3. Execute sync multiple times
4. Measure time to complete full sync
5. Check memory usage

**Expected Result:**
- Sync completes in reasonable time
- No memory exhaustion
- Server remains responsive

---

### Test 8.2: Concurrent Sync Prevention
**Purpose:** Verify multiple sync calls don't conflict

**Steps:**
1. Execute sync URL in multiple browser tabs simultaneously
2. Monitor database for conflicts
3. Check final state

**Expected Result:**
- No deadlocks
- No double-processing
- All products eventually synced

---

## Test Suite 9: Regression Tests

### Test 9.1: Legacy Mode Compatibility
**Purpose:** Verify switching between modes works

**Steps:**
1. Set `HISTORY_FUNC` to 'manual' (legacy mode)
2. Execute sync
3. Change to 'w_cron' (smart sync)
4. Flag products and sync
5. Check both modes work

**Expected Result:**
- Both modes functional
- No conflicts when switching

---

### Test 9.2: Upgrade Compatibility
**Purpose:** Verify smart sync works after upgrade

**Steps:**
1. Install older module version (without smart sync)
2. Upgrade to current version
3. Check `sync_status` column exists
4. Test smart sync functionality

**Expected Result:**
- Database schema updated correctly
- Smart sync functional post-upgrade

---

## Database Queries for Testing

**Check pending products count:**
```sql
SELECT COUNT(*) FROM ps_omniversepricing_products WHERE sync_status = 'pending';
```

**Check synced products today:**
```sql
SELECT COUNT(*) FROM ps_omniversepricing_products WHERE sync_status = 'synced' AND last_sync_date = CURDATE();
```

**View pending products:**
```sql
SELECT product_id, sync_status, last_sync_date FROM ps_omniversepricing_products WHERE sync_status = 'pending' LIMIT 20;
```

**Reset all products to pending:**
```sql
UPDATE ps_omniversepricing_products SET sync_status = 'pending', last_sync_date = NULL WHERE shop_id = 1;
```

**Check sync status distribution:**
```sql
SELECT sync_status, COUNT(*) as count, MIN(last_sync_date) as oldest, MAX(last_sync_date) as newest
FROM ps_omniversepricing_products
GROUP BY sync_status;
```

---

## Test Results Template

| Test ID | Test Name | Status | Notes | Date |
|---------|-----------|--------|-------|------|
| 1.1 | Product Update Flags | PASS/FAIL | | |
| 1.2 | Combination Update | PASS/FAIL | | |
| 2.1 | Manual Sync Button | PASS/FAIL | | |
| 2.2 | Cron URL Execution | PASS/FAIL | | |
| 2.3 | Batch Processing | PASS/FAIL | | |
| 3.1 | CPU Safeguard | PASS/FAIL | | |
| ... | ... | ... | ... | ... |

---

## Known Limitations

1. **Time Limit:** Sync stops after 50 seconds to prevent timeout
2. **Batch Size:** Processes max 500 products per batch
3. **Server Load:** Skips execution if load average > 5.0
4. **Shop Context:** Must be in correct shop context for multi-store setups

---

## Troubleshooting

**Sync not processing:**
- Check `HISTORY_FUNC` is set to 'w_cron'
- Verify products have `sync_status = 'pending'`
- Check PHP error logs
- Verify cron URL is accessible

**Products stuck pending:**
- Manually execute sync URL multiple times
- Check database for orphaned entries
- Verify `last_sync_date` updates

**Performance issues:**
- Reduce `PRODUCT_BATCH_SIZE` in code
- Check server load average
- Verify database indexes on `sync_status` column
