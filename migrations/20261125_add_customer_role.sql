ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','manager','warehouse','technician','staff','customer')
    NOT NULL DEFAULT 'customer';
