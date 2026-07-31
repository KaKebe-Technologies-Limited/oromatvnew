-- ============================================================
-- Oroma TV — sync user accounts to production
-- Run this in phpMyAdmin (or via SSH mysql client) against the
-- LIVE database: u850523537_OromaTVDB
--
-- What it does:
--   1. Resets admin@oromatv.com's password to Oroma@2016 (role stays admin)
--   2. Creates 3 new accounts (2 editors + 1 admin), all with password Oroma@2016
-- Safe to re-run: existing rows are updated in place, not duplicated.
-- ============================================================

-- 1. Reset existing admin's password
UPDATE users
SET password = '$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i'
WHERE email = 'admin@oromatv.com';

-- 2. New accounts (password for all three: Oroma@2016)
INSERT INTO users (name, email, password, role)
VALUES
    ('Staff Writer', 'admin1@oromatv.com', '$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i', 'editor'),
    ('Contributing Writer', 'admin2@oromatv.com', '$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i', 'editor'),
    ('Oroma Admin', 'admin3@oromatv.com', '$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i', 'admin')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password = VALUES(password),
    role = VALUES(role);
