#!/bin/bash
 
# CONFIGURA ESTO:
DB_USER="chorizosql"
DB_PASS="My_Chorizo"
DB_NAME="dbchorizosql"
DB_HOST="10.11.0.16"
 
mysql -u"$DB_USER" -p"$DB_PASS" -h "$DB_HOST" "$DB_NAME" <<'EOF'
USE dbchorizosql;
 
DELIMITER $$
 
/* ===========================
   TRIGGER: AFTER INSERT
=========================== */
DROP TRIGGER IF EXISTS trg_ldap_users_ai_sync_wp $$
CREATE TRIGGER trg_ldap_users_ai_sync_wp
AFTER INSERT ON ldap_users
FOR EACH ROW
BEGIN
    DECLARE v_now       DATETIME;
    DECLARE v_wp_pass   VARCHAR(255);
    DECLARE v_groups_lc VARCHAR(1024);
    DECLARE v_role      VARCHAR(20);
    DECLARE v_caps      VARCHAR(255);
    DECLARE v_level     INT;
    DECLARE v_user_id   BIGINT;
 
    SET v_now       = NOW();
    SET v_groups_lc = LOWER(COALESCE(NEW.groups_text, ''));
    SET v_role      = NULL;
 
    -- Mapear grupos LDAP → roles de WordPress
    IF v_groups_lc LIKE '%admins%' THEN
        SET v_role  = 'administrator';
        SET v_caps  = 'a:1:{s:13:"administrator";b:1;}';
        SET v_level = 10;
    ELSEIF v_groups_lc LIKE '%chorizados%' THEN
        SET v_role  = 'subscriber';
        SET v_caps  = 'a:1:{s:10:"subscriber";b:1;}';
        SET v_level = 0;
    END IF;
 
    IF v_role IS NOT NULL THEN
 
        -- Convertir {MD5}BASE64 → MD5 HEX para WP
        IF NEW.ldap_password IS NOT NULL
           AND NEW.ldap_password <> ''
           AND NEW.ldap_password LIKE '{MD5}%'
        THEN
            SET v_wp_pass = LOWER(
                HEX(FROM_BASE64(SUBSTRING(NEW.ldap_password, 6)))
            );
        ELSE
            SET v_wp_pass = NEW.ldap_password;
        END IF;
 
        -- ¿Ya existe user_login en wp_users?
        SELECT ID INTO v_user_id
        FROM wordpress_db.wp_users
        WHERE user_login = NEW.uid
        LIMIT 1;
 
        IF v_user_id IS NULL THEN
            -- INSERT nuevo usuario WP
            INSERT INTO wordpress_db.wp_users (
                user_login, user_pass, user_nicename,
                user_email, user_registered, user_status, display_name
            )
            VALUES (
                NEW.uid,
                v_wp_pass,
                NEW.uid,
                COALESCE(NEW.mail, CONCAT(NEW.uid, '@example.local')),
                v_now,
                0,
                NEW.cn
            );
 
            SET v_user_id = LAST_INSERT_ID();
        ELSE
            -- UPDATE usuario WP existente
            UPDATE wordpress_db.wp_users
               SET user_pass     = v_wp_pass,
                   user_email    = COALESCE(NEW.mail, CONCAT(NEW.uid, '@example.local')),
                   display_name  = NEW.cn,
                   user_nicename = NEW.uid
             WHERE ID = v_user_id;
        END IF;
 
        -- Sincronizar roles (wp_capabilities / wp_user_level)
        DELETE FROM wordpress_db.wp_usermeta
         WHERE user_id = v_user_id
           AND meta_key IN ('wp_capabilities', 'wp_user_level');
 
        INSERT INTO wordpress_db.wp_usermeta (user_id, meta_key, meta_value)
        VALUES (v_user_id, 'wp_capabilities', v_caps);
 
        INSERT INTO wordpress_db.wp_usermeta (user_id, meta_key, meta_value)
        VALUES (v_user_id, 'wp_user_level', v_level);
 
    END IF;
END$$
 
 
/* ===========================
   TRIGGER: AFTER UPDATE
=========================== */
DROP TRIGGER IF EXISTS trg_ldap_users_au_sync_wp $$
CREATE TRIGGER trg_ldap_users_au_sync_wp
AFTER UPDATE ON ldap_users
FOR EACH ROW
BEGIN
    DECLARE v_wp_pass   VARCHAR(255);
    DECLARE v_groups_lc VARCHAR(1024);
    DECLARE v_role      VARCHAR(20);
    DECLARE v_caps      VARCHAR(255);
    DECLARE v_level     INT;
    DECLARE v_user_id   BIGINT;
 
    SET v_groups_lc = LOWER(COALESCE(NEW.groups_text, ''));
    SET v_role      = NULL;
 
    -- Roles según grupos
    IF v_groups_lc LIKE '%admins%' THEN
        SET v_role  = 'administrator';
        SET v_caps  = 'a:1:{s:13:"administrator";b:1;}';
        SET v_level = 10;
    ELSEIF v_groups_lc LIKE '%chorizados%' THEN
        SET v_role  = 'subscriber';
        SET v_caps  = 'a:1:{s:10:"subscriber";b:1;}';
        SET v_level = 0;
    END IF;
 
    -- Si ya no pertenece a ninguno de esos grupos → borrar de WP
    IF v_role IS NULL THEN
 
        SELECT ID INTO v_user_id
        FROM wordpress_db.wp_users
        WHERE user_login = NEW.uid
        LIMIT 1;
 
        IF v_user_id IS NOT NULL THEN
            DELETE FROM wordpress_db.wp_usermeta WHERE user_id = v_user_id;
            DELETE FROM wordpress_db.wp_users    WHERE ID      = v_user_id;
        END IF;
 
    ELSE
        -- Sigue con rol válido → sincronizar datos
 
        IF NEW.ldap_password IS NOT NULL
           AND NEW.ldap_password <> ''
           AND NEW.ldap_password LIKE '{MD5}%'
        THEN
            SET v_wp_pass = LOWER(
                HEX(FROM_BASE64(SUBSTRING(NEW.ldap_password, 6)))
            );
        ELSE
            SET v_wp_pass = NEW.ldap_password;
        END IF;
 
        SELECT ID INTO v_user_id
        FROM wordpress_db.wp_users
        WHERE user_login = NEW.uid
        LIMIT 1;
 
        IF v_user_id IS NULL THEN
            -- INSERT usuario WP
            INSERT INTO wordpress_db.wp_users (
                user_login, user_pass, user_nicename,
                user_email, user_registered, user_status, display_name
            )
            VALUES (
                NEW.uid,
                v_wp_pass,
                NEW.uid,
                COALESCE(NEW.mail, CONCAT(NEW.uid, '@example.local')),
                NOW(),
                0,
                NEW.cn
            );
 
            SET v_user_id = LAST_INSERT_ID();
        ELSE
            -- UPDATE usuario WP
            UPDATE wordpress_db.wp_users
               SET user_pass     = v_wp_pass,
                   user_email    = COALESCE(NEW.mail, CONCAT(NEW.uid, '@example.local')),
                   display_name  = NEW.cn,
                   user_nicename = NEW.uid
             WHERE ID = v_user_id;
        END IF;
 
        -- Roles en wp_usermeta
        DELETE FROM wordpress_db.wp_usermeta
         WHERE user_id = v_user_id
           AND meta_key IN ('wp_capabilities', 'wp_user_level');
 
        INSERT INTO wordpress_db.wp_usermeta (user_id, meta_key, meta_value)
        VALUES (v_user_id, 'wp_capabilities', v_caps);
 
        INSERT INTO wordpress_db.wp_usermeta (user_id, meta_key, meta_value)
        VALUES (v_user_id, 'wp_user_level', v_level);
 
    END IF;
END$$
 
 
/* ===========================
   TRIGGER: AFTER DELETE
=========================== */
DROP TRIGGER IF EXISTS trg_ldap_users_ad_sync_wp $$
CREATE TRIGGER trg_ldap_users_ad_sync_wp
AFTER DELETE ON ldap_users
FOR EACH ROW
BEGIN
    DECLARE v_user_id BIGINT;
 
    SELECT ID INTO v_user_id
    FROM wordpress_db.wp_users
    WHERE user_login = OLD.uid
    LIMIT 1;
 
    IF v_user_id IS NOT NULL THEN
        DELETE FROM wordpress_db.wp_usermeta WHERE user_id = v_user_id;
        DELETE FROM wordpress_db.wp_users    WHERE ID      = v_user_id;
    END IF;
END$$
 
DELIMITER ;
 
EOF
 
echo "=== Triggers created/updated successfully ==="