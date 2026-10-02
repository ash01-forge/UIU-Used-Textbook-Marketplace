-- Capture the listing price when a meetup is completed. Existing completed
-- transactions remain NULL: their historical price cannot be reconstructed.
ALTER TABLE `purchase_requests`
    ADD COLUMN IF NOT EXISTS `sale_price` DECIMAL(10,2) NULL DEFAULT NULL AFTER `completed_at`;
