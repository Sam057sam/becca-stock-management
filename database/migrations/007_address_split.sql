-- Split single address field into components for locations, suppliers, customers

ALTER TABLE locations
  ADD COLUMN IF NOT EXISTS address_line VARCHAR(255) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER address_line,
  ADD COLUMN IF NOT EXISTS state VARCHAR(100) NULL AFTER city,
  ADD COLUMN IF NOT EXISTS zipcode VARCHAR(20) NULL AFTER state,
  ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL AFTER zipcode;

ALTER TABLE suppliers
  ADD COLUMN IF NOT EXISTS address_line VARCHAR(255) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER address_line,
  ADD COLUMN IF NOT EXISTS state VARCHAR(100) NULL AFTER city,
  ADD COLUMN IF NOT EXISTS zipcode VARCHAR(20) NULL AFTER state,
  ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL AFTER zipcode;

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS address_line VARCHAR(255) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER address_line,
  ADD COLUMN IF NOT EXISTS state VARCHAR(100) NULL AFTER city,
  ADD COLUMN IF NOT EXISTS zipcode VARCHAR(20) NULL AFTER state,
  ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL AFTER zipcode;

-- Migrate old single 'address' data to new 'address_line' if present
UPDATE locations SET address_line = COALESCE(address_line, address) WHERE address IS NOT NULL;
UPDATE suppliers SET address_line = COALESCE(address_line, address) WHERE address IS NOT NULL;
UPDATE customers SET address_line = COALESCE(address_line, address) WHERE address IS NOT NULL;

