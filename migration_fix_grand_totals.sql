-- Fix incorrect grand_total values in bills table
-- grand_total should be: unpaid extensions + unpaid orders (excluding already-paid room cost)
-- is_paid should be: 0 if grand_total > 0, else 1

-- First, ensure is_paid columns exist on orders and rental_extensions
-- (Run migration_add_is_paid_flags.sql first if not done)

-- Create a temporary table to calculate correct values
CREATE TEMPORARY TABLE IF NOT EXISTS temp_bill_fixes AS
SELECT 
    b.bill_id,
    b.rental_id,
    b.total_room_cost,
    COALESCE((SELECT SUM(oi.price * oi.quantity) 
              FROM order_items oi 
              JOIN orders o ON oi.order_id = o.order_id 
              WHERE o.rental_id = b.rental_id AND o.is_paid = 0), 0) as correct_orders_total,
    COALESCE((SELECT SUM(cost) 
              FROM rental_extensions 
              WHERE rental_id = b.rental_id AND is_paid = 0), 0) as correct_ext_total
FROM bills b;

-- Update bills with correct totals and is_paid flag
UPDATE bills b
JOIN temp_bill_fixes tf ON b.bill_id = tf.bill_id
SET 
    b.total_orders_cost = tf.correct_orders_total,
    b.grand_total = tf.correct_orders_total + tf.correct_ext_total,
    b.is_paid = IF((tf.correct_orders_total + tf.correct_ext_total) > 0, 0, 1);

-- Clean up
DROP TEMPORARY TABLE IF EXISTS temp_bill_fixes;

-- Display results
SELECT 
    b.bill_id,
    b.rental_id,
    b.total_room_cost,
    b.total_orders_cost,
    b.grand_total,
    b.is_paid,
    (SELECT SUM(oi.price * oi.quantity) FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE o.rental_id = b.rental_id AND o.is_paid = 0) as actual_unpaid_orders,
    (SELECT SUM(cost) FROM rental_extensions WHERE rental_id = b.rental_id AND is_paid = 0) as actual_unpaid_extensions,
    (SELECT COUNT(*) FROM transactions WHERE bill_id = b.bill_id) as payment_count
FROM bills b
ORDER BY b.bill_id DESC
LIMIT 20;
