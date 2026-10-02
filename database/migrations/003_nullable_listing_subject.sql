-- Older installations retained a NOT NULL listing subject from the original schema.
-- The API and fresh-install schema allow an omitted subject. Preserve all values.
ALTER TABLE `listings`
    MODIFY COLUMN `subject` VARCHAR(100) NULL DEFAULT NULL;
