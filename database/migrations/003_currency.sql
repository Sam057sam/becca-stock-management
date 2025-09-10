INSERT INTO settings(`key`,`value`) VALUES
  ('currency_symbol','$'),
  ('currency_code','USD')
ON DUPLICATE KEY UPDATE `value`=`value`;

