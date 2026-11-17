USE hayatisayang;

UPDATE login SET nama = '' WHERE nama IS NULL;
UPDATE login SET no_hp = '' WHERE no_hp IS NULL;

ALTER TABLE login
    CHANGE COLUMN id_login user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    CHANGE COLUMN no_hp nomor_hp VARCHAR(30) NOT NULL DEFAULT '' AFTER nama,
    MODIFY COLUMN nama VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    MODIFY password VARCHAR(255) NOT NULL;

SOURCE database/schema.sql;
