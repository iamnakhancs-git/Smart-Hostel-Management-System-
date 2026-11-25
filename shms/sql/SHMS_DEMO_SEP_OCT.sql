-- SHMS DEMO DATA - SEPTEMBER / OCTOBER 2026
-- Import SECOND, once, after SHMS_DATABASE_CLEAN.sql on a fresh database.
-- Contains 10 named residents, 10 rooms, 10 allocations and 61 menu days.
-- Join/allocation date: 1 September 2026; menu ends 31 October 2026.
-- Resident password: resident123. Amna email: resident@shms.com.
-- Other resident emails: lowercase name @shms.com.
-- No attendance or generated bills are seeded; use the application to create them.
-- No database/table DROP or CREATE statements in this file.

USE `centralized_hostel_db`;

START TRANSACTION;

-- Create sample rooms before allocating residents.
INSERT INTO `rooms` (`room_number`,`room_type_id`,`floor`) VALUES
('101',1,1),('102',1,1),('103',2,1),('104',2,1),('105',3,1),
('201',1,2),('202',1,2),('203',2,2),('204',3,2),('205',3,2);


-- Complete resident records. Emails, phones and CNICs are unique demo values.
INSERT INTO `residents`
(`name`,`email`,`password`,`phone`,`emergency_contact`,`address`,`id_proof_type`,`id_proof_number`,`join_date`,`status`) VALUES
('Amna','resident@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000001','03110000001','Hostel Campus, Multan','CNIC','3520212345601','2026-09-01','active'),
('Insa','insa@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000002','03110000002','Hostel Campus, Multan','CNIC','3520212345602','2026-09-01','active'),
('Noor','noor@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000003','03110000003','Hostel Campus, Multan','CNIC','3520212345603','2026-09-01','active'),
('Maila','maila@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000004','03110000004','Hostel Campus, Multan','CNIC','3520212345604','2026-09-01','active'),
('Abeer','abeer@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000005','03110000005','Hostel Campus, Multan','CNIC','3520212345605','2026-09-01','active'),
('Maida','maida@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000006','03110000006','Hostel Campus, Multan','CNIC','3520212345606','2026-09-01','active'),
('Manza','manza@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000007','03110000007','Hostel Campus, Multan','CNIC','3520212345607','2026-09-01','active'),
('Ayesha','ayesha@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000008','03110000008','Hostel Campus, Multan','CNIC','3520212345608','2026-09-01','active'),
('Fatima','fatima@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000009','03110000009','Hostel Campus, Multan','CNIC','3520212345609','2026-09-01','active'),
('Insha','insha@shms.com','$2y$10$UzUVi4x9gC24MQ8szYPfI.UEJ3xwCulBqUPBydfeDStAN1NFuk6xu','03000000010','03110000010','Hostel Campus, Multan','CNIC','3520212345610','2026-09-01','active')
ON DUPLICATE KEY UPDATE
`name`=VALUES(`name`),`password`=VALUES(`password`),`phone`=VALUES(`phone`),
`emergency_contact`=VALUES(`emergency_contact`),`address`=VALUES(`address`),
`id_proof_type`=VALUES(`id_proof_type`),`id_proof_number`=VALUES(`id_proof_number`),
`join_date`=VALUES(`join_date`),`status`='active';

-- Allocate according to room capacity: A=1, B=2, C=3 beds.

INSERT INTO `room_allocations` (`resident_id`,`room_id`,`alloc_date`,`is_active`)
SELECT `r`.`id`,`rm`.`id`,'2026-09-01',1
FROM (
  SELECT 'resident@shms.com' `email`,'101' `room_number` UNION ALL
  SELECT 'insa@shms.com','102' UNION ALL
  SELECT 'noor@shms.com','103' UNION ALL
  SELECT 'maila@shms.com','103' UNION ALL
  SELECT 'abeer@shms.com','104' UNION ALL
  SELECT 'maida@shms.com','104' UNION ALL
  SELECT 'manza@shms.com','105' UNION ALL
  SELECT 'ayesha@shms.com','105' UNION ALL
  SELECT 'fatima@shms.com','105' UNION ALL
  SELECT 'insha@shms.com','201'
) `map`
JOIN `residents` `r` ON `r`.`email`=`map`.`email`
JOIN `rooms` `rm` ON `rm`.`room_number`=`map`.`room_number`;

UPDATE `rooms` SET `status`='occupied'
WHERE `room_number` IN ('101','102','103','104','105','201');

-- Default/current meal prices.
UPDATE `meal_prices` SET `price`=60.00  WHERE `meal_type`='breakfast';
UPDATE `meal_prices` SET `price`=120.00 WHERE `meal_type`='lunch';
UPDATE `meal_prices` SET `price`=50.00  WHERE `meal_type`='snacks';
UPDATE `meal_prices` SET `price`=130.00 WHERE `meal_type`='dinner';


-- Generate every date from 01 September through 31 October 2026 (61 days).
-- All four meals are enabled; dishes rotate by day of week.
INSERT INTO `mess_daily_menu`
(`menu_date`,`breakfast`,`lunch`,`snacks`,`dinner`,
 `breakfast_enabled`,`lunch_enabled`,`snacks_enabled`,`dinner_enabled`,
 `breakfast_price`,`lunch_price`,`snacks_price`,`dinner_price`,`updated_by`)
SELECT `d`,
  CASE DAYOFWEEK(`d`)
    WHEN 1 THEN 'Halwa Puri & Tea' WHEN 2 THEN 'Paratha & Tea'
    WHEN 3 THEN 'Omelette, Bread & Tea' WHEN 4 THEN 'Chana & Paratha'
    WHEN 5 THEN 'Boiled Egg, Bread & Tea' WHEN 6 THEN 'Aloo Paratha & Tea'
    ELSE 'Fried Egg, Bread & Tea' END,
  CASE DAYOFWEEK(`d`)
    WHEN 1 THEN 'Chicken Biryani & Raita' WHEN 2 THEN 'Daal Chawal & Salad'
    WHEN 3 THEN 'Chicken Karahi & Roti' WHEN 4 THEN 'Vegetable Pulao & Raita'
    WHEN 5 THEN 'Daal Mash & Roti' WHEN 6 THEN 'Chicken Pulao & Salad'
    ELSE 'Aloo Gosht & Roti' END,
  CASE DAYOFWEEK(`d`)
    WHEN 1 THEN 'Samosa & Tea' WHEN 2 THEN 'Biscuits & Tea'
    WHEN 3 THEN 'Fruit Chaat' WHEN 4 THEN 'Pakora & Tea'
    WHEN 5 THEN 'Sandwich & Tea' WHEN 6 THEN 'Cake & Tea'
    ELSE 'Chana Chaat' END,
  CASE DAYOFWEEK(`d`)
    WHEN 1 THEN 'Daal Chana & Roti' WHEN 2 THEN 'Chicken Qorma & Roti'
    WHEN 3 THEN 'Mix Vegetable & Roti' WHEN 4 THEN 'Chicken Biryani & Raita'
    WHEN 5 THEN 'Lobia & Roti' WHEN 6 THEN 'Chicken Handi & Roti'
    ELSE 'Daal Moong & Roti' END,
  1,1,1,1,60.00,120.00,50.00,130.00,1
FROM (
  SELECT DATE_ADD('2026-09-01',INTERVAL (`t`.`n`*10+`o`.`n`) DAY) `d`
  FROM
    (SELECT 0 `n` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) `t`
  CROSS JOIN
    (SELECT 0 `n` UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) `o`
) `dates`
WHERE `d` <= '2026-10-31'
ON DUPLICATE KEY UPDATE
`breakfast`=VALUES(`breakfast`),`lunch`=VALUES(`lunch`),
`snacks`=VALUES(`snacks`),`dinner`=VALUES(`dinner`),
`breakfast_enabled`=1,`lunch_enabled`=1,`snacks_enabled`=1,`dinner_enabled`=1,
`breakfast_price`=60.00,`lunch_price`=120.00,`snacks_price`=50.00,`dinner_price`=130.00,
`updated_by`=1;

COMMIT;

-- Verification: expected totals are 10 residents, 10 active allocations and 61 menu days.
SELECT COUNT(*) AS `requested_residents`
FROM `residents`
WHERE `email` IN ('resident@shms.com','insa@shms.com','noor@shms.com','maila@shms.com','abeer@shms.com','maida@shms.com','manza@shms.com','ayesha@shms.com','fatima@shms.com','insha@shms.com');

SELECT COUNT(*) AS `active_allocations`
FROM `room_allocations` `ra`
JOIN `residents` `r` ON `r`.`id`=`ra`.`resident_id`
WHERE `ra`.`is_active`=1
AND `r`.`email` IN ('resident@shms.com','insa@shms.com','noor@shms.com','maila@shms.com','abeer@shms.com','maida@shms.com','manza@shms.com','ayesha@shms.com','fatima@shms.com','insha@shms.com');

SELECT COUNT(*) AS `menu_days`
FROM `mess_daily_menu`
WHERE `menu_date` BETWEEN '2026-09-01' AND '2026-10-31';
