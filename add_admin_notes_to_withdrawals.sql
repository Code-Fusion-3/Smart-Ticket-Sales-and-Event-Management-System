-- Add admin_notes column to withdrawals table
ALTER TABLE withdrawals ADD COLUMN admin_notes TEXT AFTER payment_details;