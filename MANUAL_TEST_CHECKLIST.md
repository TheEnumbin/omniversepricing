# Manual Testing Checklist - Customer Group & Property-wise Tracking

**Branch**: `fix_customer_group`
**Module**: OmniversePricing
**Focus**: Specific price tracking for customer groups, currencies, countries, and combinations

---

## Pre-Test Setup

- [x] Ensure PrestaShop is in debug mode
- [x] Backup database before testing
- [x] Clear module cache
- [x] Module version should reflect changes (1.3.1+)
- [x] Create test customer groups (if not exist)
- [x] Create test currencies (if not exist)
- [x] Ensure there are products with combinations
- [x] Ensure there are products with specific prices set up

---

## Test Category 1: Customer Group Specific Prices

### Test 1.1: Create Product with Customer Group Specific Price
**Steps:**
1. Go to Catalog → Products
2. Create/Edit a product
3. Add specific price for a customer group (e.g., "Wholesale" customers)
4. Set group-specific discount (e.g., 20% off)
5. Save product
6. Run manual sync via admin or visit sync URL

**Expected Result:**
- [x] Price history entry created with correct `id_group`
- [x] Price amount reflects the group-specific discounted price
- [x] Database shows entry with `id_group` = wholesale group ID
- [x] Frontend shows correct price for customers in that group


### Test 1.2: Multiple Customer Groups for Same Product
**Steps:**
1. Create product with specific prices for 3 different customer groups
2. Set different prices for each group:
   - Group A: 10% discount
   - Group B: 20% discount
   - Group C: €5 fixed discount
3. Run sync
4. Check database and frontend

**Expected Result:**
- [x] 3 separate entries in omniversepricing table
- [x] Each entry has correct `id_group` and price
- [x] Prices match the specific discounts for each group
- [x] No duplicate entries for same group+product combination

### Test 1.3: Default Price When No Group Specific Price Exists
**Steps:**
1. Create product WITHOUT group-specific price
2. Run sync
3. Check database

**Expected Result:**
- [x] Entry created with `id_group` = 0
- [x] Price equals default product price
- [x] Frontend shows default price for all groups

---

## Test Category 2: Currency Specific Prices

### Test 2.1: Product with Currency-Specific Price
**Steps:**
1. Create product
2. Add specific price in USD (if default is EUR)
3. Set USD-specific amount (e.g., $50)
4. Run sync
5. Check database

**Expected Result:**
- [x] Entry created with correct `id_currency` = USD currency ID
- [x] Price is stored in BASE currency (EUR), not USD
- [x] Verify: `price` column shows converted EUR amount
- [x] Frontend displays correct price when viewed in USD

### Test 2.2: Multiple Currencies for Same Product
**Steps:**
1. Create product with specific prices in 3 currencies:
   - EUR (default): €100
   - USD: $120
   - GBP: £90
2. Run sync
3. Verify database

**Expected Result:**
- [x] 3 entries with different `id_currency` values
- [x] ALL prices stored in base currency (EUR)
- [x] Frontend converts correctly when displaying:
   - EUR view: shows €100
   - USD view: shows ~$120 (using current rate)
   - GBP view: shows ~£90 (using current rate)


## Test Category 4: Product Combinations (Attributes)

### Test 4.1: Product with Combinations and Default Prices
**Steps:**
1. Create product with size combinations (S, M, L, XL)
2. Set different prices for each combination
3. Run sync
4. Check database

**Expected Result:**
- [x] Entries for EACH combination (id_product_attribute matches)
- [x] Each entry has correct price for that combination
- [x] Frontend shows correct prices for each size variant
- [x] Price history chart shows data for all variants

### Test 4.2: Combination with Specific Price Override
**Steps:**
1. Create product with combinations
2. Add specific price to ONE combination (e.g., Size L)
3. Give Size L a special discount
4. Run sync
5. Verify database

**Expected Result:**
- [x] Size L entry has discounted price
- [x] Other sizes (S, M, XL) have regular prices
- [x] No duplicate entries for Size L
- [x] Specific price takes precedence over default combination price

### Test 4.3: Base Product vs Combination Specific Prices
**Steps:**
1. Create product with combinations
2. Add specific price to BASE product (id_product_attribute = 0)
3. Add different specific price to ONE combination
4. Run sync

**Expected Result:**
- [x] Base specific price applies to all combinations WITHOUT specific price
- [x] Combination-specific price takes precedence
- [x] No duplicate or conflicting entries
- [x] `id_product_attribute = 0` entry exists (base price)
- [x] `id_product_attribute = [specific_id]` entry exists


## Test Category 9: Performance & Data Integrity

### Test 9.1: Large Dataset Sync
**Steps:**
1. Ensure 1000+ products with various specific prices
2. Run full sync
3. Monitor execution time and memory

**Expected Result:**
- [ ] Sync completes within PHP time limit (50s)
- [ ] No memory exhaustion
- [ ] No duplicate entries created

### Test 9.2: Database Query Performance
**Steps:**
1. Run EXPLAIN on sync queries
2. Check query execution times

**Verify Query Performance:**
```sql
EXPLAIN SELECT * FROM ps_omniversepricing_products
WHERE product_id = 123
AND id_group = 2;
```

**Expected Result:**
- [ ] Queries use indexes where available
- [ ] Execution time < 100ms for typical queries

### Test 9.3: Data Consistency
**Steps:**
1. Run sync
2. Count products in catalog vs. omniversepricing table
3. Verify expected counts match

**Expected Result:**
- [ ] Every product with specific price has corresponding entry
- [ ] No orphaned entries (products that don't exist)

---

## Test Category 10: Regression Tests

### Test 10.1: Backward Compatibility
**Steps:**
1. Test with existing data from previous version
2. Ensure old entries still display correctly
3. Run new sync

**Expected Result:**
- [ ] Existing price history still shows correctly
- [ ] New sync doesn't break old data
- [ ] No data migration errors

### Test 10.2: Module Install/Uninstall
**Steps:**
1. Uninstall module
2. Reinstall module
3. Run sync
4. Verify functionality

**Expected Result:**
- [ ] Clean install works
- [ ] Hooks registered correctly
- [ ] Sync functions properly after reinstall

---

## Post-Test Verification

### Database Integrity Checks
**Run these queries to verify data integrity:**

```sql
-- Check for duplicates
SELECT product_id, id_product_attribute, id_country, id_currency, id_group, COUNT(*) as count
FROM ps_omniversepricing_products
GROUP BY product_id, id_product_attribute, id_country, id_currency, id_group
HAVING count > 1;

-- Should return 0 rows

-- Verify specific prices are tracked
SELECT COUNT(DISTINCT CONCAT(product_id, '-', id_product_attribute, '-', id_country, '-', id_currency, '-', id_group)) as unique_combinations,
       COUNT(*) as total_entries
FROM ps_omniversepricing_products;

-- unique_combinations should equal total_entries

-- Check for zero prices (should be none)
SELECT COUNT(*) FROM ps_omniversepricing_products WHERE price = 0 OR price IS NULL;

-- Should return 0

-- Verify base currency storage
SELECT
    id_currency,
    COUNT(*) as count,
    AVG(price) as avg_price
FROM ps_omniversepricing_products
GROUP BY id_currency;

-- Most prices should be stored with id_currency matching base/default currency
```

### Frontend Verification
- [ ] Product pages display correctly for all test scenarios
- [ ] Price history charts load and show data
- [ ] Currency switching works correctly
- [ ] Customer group pricing visible for logged-in customers

### Log Files Check
- [ ] No PHP errors in error log
- [ ] No database query errors
- [ ] Module execution logs clean

---