# How to Create Stripe Price IDs

This guide explains how to create Stripe price IDs for courses in your system.

## What is a Stripe Price ID?

A Stripe Price ID is a unique identifier that starts with `price_` (e.g., `price_1ABC123xyz`). It's used to identify a specific price for a product in Stripe's system.

## Methods to Create Stripe Price IDs

### Method 1: Using Artisan Command (Recommended)

We've created an Artisan command to easily create Stripe price IDs:

#### Create prices for a specific course:
```bash
php artisan stripe:create-prices --course=1
```
(Replace `1` with your course ID)

#### Create prices for all courses:
```bash
php artisan stripe:create-prices --all
```

#### Create prices only for courses missing Stripe price IDs:
```bash
php artisan stripe:create-prices --missing
```

### Method 2: Using the Job Directly

You can dispatch the `CreateStripePrice` job programmatically:

```php
use App\Jobs\CreateStripePrice;

// In a controller or tinker
dispatch(new CreateStripePrice($courseId));
```

### Method 3: Automatic Creation (On-the-fly)

The system automatically creates Stripe prices when:
- A user tries to purchase a course that has a `CoursePrice` record but no valid `stripe_price_id`
- The price ID is missing or invalid (doesn't start with `price_`)

This happens in:
- `CoursePurchaseController::parentCheckout()`
- `CoursePurchaseController::purchaseCourse()`
- `CartController::add()` (for some products)

### Method 4: Manual Creation via Stripe Dashboard

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Products** → **Add Product**
3. Create a product with:
   - Name: Course name
   - Description: Course description
   - Images: Course image (optional)
4. Add a Price:
   - Amount: Course price (in cents, e.g., £14.65 = 1465)
   - Currency: GBP
   - Billing: Recurring (monthly)
   - Interval: Based on billing period
5. Copy the Price ID (starts with `price_`)
6. Update your database:
   ```sql
   UPDATE course_prices 
   SET stripe_price_id = 'price_xxxxx' 
   WHERE id = [your_course_price_id];
   ```

## How the System Creates Stripe Prices

When creating a Stripe price, the system:

1. **Creates a Stripe Product** (if it doesn't exist):
   - Name: Course name
   - Description: Course description
   - Metadata: Mode (Online/In person)
   - Images: Course image

2. **Creates a Stripe Price**:
   - Currency: GBP (or from course price)
   - Unit Amount: Course price × 100 (converted to cents)
   - Recurring: Monthly subscription
   - Interval Count: Based on billing period
   - Product: Links to the Stripe product

3. **Updates the Database**:
   - Saves `stripe_product_id` to `course_prices` table
   - Saves `stripe_price_id` to `course_prices` table

## Requirements

1. **Stripe API Key**: Make sure `STRIPE_SECRET` is set in your `.env` file:
   ```
   STRIPE_SECRET=sk_test_xxxxx
   ```

2. **Course Data**: The course must have:
   - Modes (Online/In person)
   - Prices with billing periods
   - Amount and currency

## Troubleshooting

### Error: "Stripe API key not configured"
- Check your `.env` file has `STRIPE_SECRET` set
- Make sure you're using the correct key (test vs live)

### Error: "Billing period missing"
- Ensure each `CoursePrice` has a `billing_period_id` set
- Check that the billing period exists in the `billing_periods` table

### Price ID is null or invalid
- Run: `php artisan stripe:create-prices --missing`
- Or manually update the database with valid Stripe price IDs

### Check existing Stripe prices
```sql
SELECT id, course_id, amount, currency, stripe_price_id 
FROM course_prices 
WHERE stripe_price_id IS NULL 
   OR stripe_price_id = '' 
   OR stripe_price_id NOT LIKE 'price_%';
```

## Verification

After creating prices, verify they were created:

1. **Check the database**:
   ```sql
   SELECT id, stripe_price_id, stripe_product_id 
   FROM course_prices 
   WHERE course_id = [your_course_id];
   ```

2. **Check Stripe Dashboard**:
   - Go to Products → Find your course
   - Verify prices are listed

3. **Test via API**:
   - Try adding the course to cart
   - Check the response includes a valid `price_id`

## Notes

- Stripe Price IDs are permanent and cannot be changed
- If you need to update a price, create a new price ID
- Test mode price IDs start with `price_` and work with test API keys
- Live mode price IDs also start with `price_` but require live API keys
