-- Migration 002: add category relationship columns required by admin CRUD.
-- Safe for fresh installs and databases already upgraded by migration 001.
-- This file is intentionally not executed by frontend integration work.

ALTER TABLE `categories`
    ADD COLUMN IF NOT EXISTS `department` VARCHAR(100) NULL DEFAULT NULL AFTER `type`,
    ADD COLUMN IF NOT EXISTS `subject` VARCHAR(150) NULL DEFAULT NULL AFTER `department`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `department` VARCHAR(100) NULL DEFAULT NULL AFTER `student_id`;

-- Department category labels are their own parent label. Preserve any existing value.
UPDATE `categories`
SET `department` = `name`
WHERE `type` = 'Department' AND (`department` IS NULL OR `department` = '');

-- Keep each Subject's own label in the compatibility subject column.
UPDATE `categories`
SET `subject` = `name`
WHERE `type` = 'Subject' AND (`subject` IS NULL OR `subject` = '');

-- Infer a Subject parent only when existing listing data maps that exact label
-- to exactly one department. Ambiguous or unused subjects remain unassigned.
UPDATE `categories` AS c
JOIN (
    SELECT `subject`, MIN(`department`) AS `department`
    FROM `listings`
    WHERE `subject` IS NOT NULL AND `subject` <> ''
      AND `department` IS NOT NULL AND `department` <> ''
    GROUP BY `subject`
    HAVING COUNT(DISTINCT `department`) = 1
) AS mapping ON mapping.`subject` = c.`name`
SET c.`department` = mapping.`department`
WHERE c.`type` = 'Subject' AND (c.`department` IS NULL OR c.`department` = '');
