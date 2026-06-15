UPDATE `sales_orders`
SET `document_no` = `so_no`
WHERE (`document_no` IS NULL OR `document_no` = '')
  AND `so_no` IS NOT NULL
  AND `so_no` <> '';

UPDATE `sales_orders`
SET `document_date` = `so_date`
WHERE (`document_date` IS NULL OR `document_date` = '0000-00-00')
  AND `so_date` IS NOT NULL;

UPDATE `purchase_orders`
SET `document_no` = `po_no`
WHERE (`document_no` IS NULL OR `document_no` = '')
  AND `po_no` IS NOT NULL
  AND `po_no` <> '';

UPDATE `purchase_orders`
SET `document_date` = `po_date`
WHERE (`document_date` IS NULL OR `document_date` = '0000-00-00')
  AND `po_date` IS NOT NULL;
