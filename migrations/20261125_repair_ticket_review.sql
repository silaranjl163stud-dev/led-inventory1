ALTER TABLE repairs
    ADD COLUMN ticket VARCHAR(150) NOT NULL DEFAULT 'Repair request' AFTER id,
    ADD COLUMN reason TEXT NULL AFTER ticket,
    MODIFY COLUMN status ENUM('pending','accepted','declined','open','in_progress','resolved','cancelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN reviewed_by BIGINT NULL AFTER created_by,
    ADD COLUMN reviewed_at TIMESTAMP NULL AFTER resolved_at,
    ADD CONSTRAINT fk_repairs_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

UPDATE repairs
SET ticket = CONCAT('Legacy repair #', id),
    reason = COALESCE(issue_description, 'Existing repair ticket')
WHERE ticket = 'Repair request';
