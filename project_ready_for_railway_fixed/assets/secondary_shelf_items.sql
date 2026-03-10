-- =====================================================
-- Secondary Shelf Life Items Table
-- =====================================================
-- This table stores items separately from main dashboard
-- Created: 2025-11-19
-- =====================================================

CREATE TABLE IF NOT EXISTS `secondary_shelf_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `purchase_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `alert_date` date DEFAULT NULL,
  `alert_group` varchar(50) DEFAULT NULL COMMENT '7_day_alert, 10_day_alert, 30_day_post_alert',
  `barcode` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expiry_date` (`expiry_date`),
  KEY `idx_alert_group` (`alert_group`),
  KEY `idx_added_by` (`added_by`),
  KEY `idx_barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Note: This table is identical to 'items' table
-- but keeps Secondary Shelf Life data separate
-- =====================================================

-- =====================================================
-- Settings Table for AI Configuration
-- =====================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
('active_ai_model', 'gemini'),
('gemini_api_key', ''),
('deepseek_api_key', '');
