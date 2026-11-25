ALTER TABLE asset_hierarchy
    DROP INDEX uq_active_child,
    ADD COLUMN active_child_id BIGINT
        GENERATED ALWAYS AS (CASE WHEN status = 'installed' THEN child_id ELSE NULL END) STORED,
    ADD UNIQUE KEY uq_active_child (active_child_id);
