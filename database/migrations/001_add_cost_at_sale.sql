ALTER TABLE sale_items
  ADD COLUMN IF NOT EXISTS cost_at_sale DECIMAL(14,2) NULL AFTER unit_price;

