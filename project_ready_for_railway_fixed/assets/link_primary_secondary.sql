-- Link Primary and Secondary Shelf Life Items
-- This migration adds a foreign key to link secondary items with primary items

-- Add primary_item_id column to secondary_shelf_items table
ALTER TABLE `secondary_shelf_items` 
ADD COLUMN `primary_item_id` INT(11) NULL AFTER `id`,
ADD COLUMN `opened_date` DATE NULL AFTER `purchase_date`,
ADD INDEX `fk_primary_item` (`primary_item_id`);

-- Add foreign key constraint (optional, for data integrity)
-- ALTER TABLE `secondary_shelf_items` 
-- ADD CONSTRAINT `fk_secondary_primary` 
-- FOREIGN KEY (`primary_item_id`) REFERENCES `items`(`id`) 
-- ON DELETE SET NULL ON UPDATE CASCADE;

-- Note: Foreign key is commented out to allow flexibility
-- If you want strict data integrity, uncomment the above lines
