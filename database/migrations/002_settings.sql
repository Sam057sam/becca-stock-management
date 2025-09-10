CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB;

INSERT INTO settings(`key`,`value`) VALUES
  ('company_name','Becca Luxury Perfumes'),
  ('company_address',''),
  ('company_phone',''),
  ('company_email','')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

