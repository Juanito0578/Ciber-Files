<?php
session_start();
require_once '../../sso/sso-check.php';
require_once '../../Conf/config.php';

/* =========================================
   ACCESS CONTROL BY ROLES (LDAP)
========================================= */
$groups = [];
if (!empty($_SESSION['groups']) && is_array($_SESSION['groups'])) {
    $groups = array_map('mb_strtolower', $_SESSION['groups']);
}

$hasGroup = function (array $needles) use ($groups) {
    foreach ($needles as $g) {
        if (in_array(mb_strtolower($g), $groups, true)) {
            return true;
        }
    }
    return false;
};

$isAdmin   = $hasGroup(['admins', 'admin', 'administrators']);
$isChorizo = $hasGroup(['chorizados', 'chorizado', 'empresa']);

if (!$isAdmin && !$isChorizo) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

$uid_ldap    = $_SESSION['uid_ldap'] ?? ($_SESSION['username'] ?? 'unknown_user');
$displayUser = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'MKDEH USER';

/* =========================================
   CSRF TOKEN
========================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

/* =========================================
   LDAP HELPERS
========================================= */

/**
 * Connect to LDAP and bind as admin.
 */
function ldap_admin_connect()
{
    global $ldap_host, $admin_dn, $admin_pass;

    $conn = ldap_connect($ldap_host);
    if (!$conn) {
        throw new Exception("Cannot connect to LDAP host.");
    }
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

    if (!@ldap_bind($conn, $admin_dn, $admin_pass)) {
        throw new Exception("Cannot bind as LDAP admin: " . ldap_error($conn));
    }

    return $conn;
}

/**
 * Load posixGroup entries:
 *  - $ldapGroups for select/checklist
 *  - maps by gidNumber, by memberUid and by CN.
 */
function ldap_load_groups($conn, string $base_groups): array
{
    $ldapGroups        = [];
    $groupsByGid       = [];
    $groupsByMemberUid = [];
    $groupsByCn        = [];

    $sr = @ldap_search(
        $conn,
        $base_groups,
        '(objectClass=posixGroup)',
        ['cn', 'gidNumber', 'memberUid']
    );

    if ($sr) {
        $entries = ldap_get_entries($conn, $sr);
        $count   = $entries['count'] ?? 0;

        for ($i = 0; $i < $count; $i++) {
            $cn = $entries[$i]['cn'][0] ?? null;
            if (!$cn) {
                continue;
            }

            $gid = isset($entries[$i]['gidnumber'][0])
                ? (int)$entries[$i]['gidnumber'][0]
                : null;

            $ldapGroups[] = [
                'cn'  => $cn,
                'gid' => $gid,
            ];

            if ($gid !== null) {
                $groupsByGid[$gid] = $cn;
            }
            $groupsByCn[$cn] = $gid;

            if (!empty($entries[$i]['memberuid'])) {
                for ($j = 0, $m = $entries[$i]['memberuid']['count']; $j < $m; $j++) {
                    $uid = $entries[$i]['memberuid'][$j];
                    $groupsByMemberUid[$uid][] = $cn;
                }
            }
        }
    }

    return [$ldapGroups, $groupsByGid, $groupsByMemberUid, $groupsByCn];
}

/**
 * Load posixAccount users from LDAP.
 * También leemos employeeNumber y userPassword (hash MD5).
 */
function ldap_load_users(
    $conn,
    string $base_users,
    array $groupsByGid,
    array $groupsByMemberUid,
    bool $isAdmin,
    string $uid_ldap
): array {
    $users        = [];
    $maxUidNumber = 1000;

    $sr = @ldap_search(
        $conn,
        $base_users,
        '(objectClass=posixAccount)',
        ['dn', 'uid', 'cn', 'mail', 'uidNumber', 'gidNumber', 'employeeNumber', 'userPassword']
    );

    if ($sr) {
        $entries = ldap_get_entries($conn, $sr);
        $count   = $entries['count'] ?? 0;

        for ($i = 0; $i < $count; $i++) {
            $uid = $entries[$i]['uid'][0] ?? null;
            if (!$uid) {
                continue;
            }

            if (!$isAdmin && $uid !== $uid_ldap) {
                // limited user: see only their own entry
                continue;
            }

            $dn   = $entries[$i]['dn'];
            $cn   = $entries[$i]['cn'][0] ?? $uid;
            $mail = $entries[$i]['mail'][0] ?? '';

            // claves en minúsculas:
            $employeeNumber = $entries[$i]['employeenumber'][0] ?? '';
            $passwordHash   = $entries[$i]['userpassword'][0]   ?? '';

            $uidNumber = isset($entries[$i]['uidnumber'][0])
                ? (int)$entries[$i]['uidnumber'][0]
                : null;
            $gidNumber = isset($entries[$i]['gidnumber'][0])
                ? (int)$entries[$i]['gidnumber'][0]
                : null;

            if ($uidNumber !== null && $uidNumber > $maxUidNumber) {
                $maxUidNumber = $uidNumber;
            }

            $groups = [];
            if ($gidNumber !== null && isset($groupsByGid[$gidNumber])) {
                $groups[] = $groupsByGid[$gidNumber];
            }
            if (isset($groupsByMemberUid[$uid])) {
                foreach ($groupsByMemberUid[$uid] as $gcn) {
                    if (!in_array($gcn, $groups, true)) {
                        $groups[] = $gcn;
                    }
                }
            }

            $users[] = [
                'dn'             => $dn,
                'uid'            => $uid,
                'cn'             => $cn,
                'mail'           => $mail,
                'employeeNumber' => $employeeNumber,
                'uidNumber'      => $uidNumber,
                'gidNumber'      => $gidNumber,
                'groups'         => $groups ? implode(', ', $groups) : '',
                'passwordHash'   => $passwordHash, // hash MD5 tal cual viene de LDAP
            ];
        }
    }

    return [$users, $maxUidNumber];
}

/**
 * Generate {MD5} password for LDAP (BASE64 sobre binario MD5).
 */
function ldap_hash_md5(string $password): string
{
    // md5(..., true) devuelve el hash binario (16 bytes)
    $hashBin = md5($password, true);

    // En LDAP el formato {MD5} suele ir con el hash en base64
    return '{MD5}' . base64_encode($hashBin);
}

/**
 * Escape DN value safely.
 */
function escape_dn_value(string $value): string
{
    if (function_exists('ldap_escape')) {
        return ldap_escape($value, '', LDAP_ESCAPE_DN);
    }
    return str_replace(
        ['\\', '"', ',', '+', '<', '>', ';', '#', '='],
        ['\\5c','\\22','\\2c','\\2b','\\3c','\\3e','\\3b','\\23','\\3d'],
        $value
    );
}

/**
 * Sincronizar memberships (memberUid) de un usuario en los grupos posixGroup,
 * en base a una lista de CNs seleccionados.
 */
function ldap_sync_user_group_memberships($conn, string $base_groups, string $uid, array $selectedGroupCns): void
{
    // Normalizamos lista: quitamos vacíos y duplicados
    $selectedGroupCns = array_values(array_unique(array_filter($selectedGroupCns, function($v) {
        return trim($v) !== '';
    })));

    $sr = @ldap_search(
        $conn,
        $base_groups,
        '(objectClass=posixGroup)',
        ['cn', 'memberUid']
    );

    if (!$sr) {
        return;
    }

    $entries = ldap_get_entries($conn, $sr);
    $count   = $entries['count'] ?? 0;

    for ($i = 0; $i < $count; $i++) {
        $gdn = $entries[$i]['dn'];
        $cn  = $entries[$i]['cn'][0] ?? null;
        if (!$cn) {
            continue;
        }

        // Cogemos todos los memberUid actuales
        $members = [];
        if (!empty($entries[$i]['memberuid'])) {
            for ($j = 0, $m = $entries[$i]['memberuid']['count']; $j < $m; $j++) {
                $members[] = $entries[$i]['memberuid'][$j];
            }
        }

        $members = array_values(array_unique($members));
        $wasMember = in_array($uid, $members, true);
        $shouldBeMember = in_array($cn, $selectedGroupCns, true);

        $changed = false;

        if ($shouldBeMember && !$wasMember) {
            // añadir uid
            $members[] = $uid;
            $changed = true;
        } elseif (!$shouldBeMember && $wasMember) {
            // quitar uid
            $members = array_values(array_filter($members, fn($m) => $m !== $uid));
            $changed = true;
        }

        if ($changed) {
            if (!empty($members)) {
                $mods = ['memberUid' => $members];
            } else {
                // borrar atributo memberUid si se queda vacío
                $mods = ['memberUid' => []];
            }

            @ldap_modify($conn, $gdn, $mods);
            // Si falla, no lanzamos excepción para no romper todo el panel.
        }
    }
}

/* =========================================
   MAIN PANEL LOGIC
========================================= */

$panelError        = null;
$panelMessage      = null;
$ldapGroups        = [];
$users             = [];
$maxUidNumber      = 1000;
$groupsByGid       = [];
$groupsByMemberUid = [];
$groupsByCn        = [];

try {
    $conn = ldap_admin_connect();

    // Load groups
    [$ldapGroups, $groupsByGid, $groupsByMemberUid, $groupsByCn] =
        ldap_load_groups($conn, $base_groups);

    // Load users (desde LDAP)
    [$users, $maxUidNumber] =
        ldap_load_users($conn, $base_users, $groupsByGid, $groupsByMemberUid, $isAdmin, $uid_ldap);

    // =======================
    //   HANDLE POST ACTIONS
    // =======================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
        ) {
            throw new Exception("Invalid CSRF token. Please try again.");
        }

        $action = $_POST['action'] ?? '';

        /* ========= SYNC DB (admins only, botón) ========= */
        if ($action === 'sync_db') {
            if (!$isAdmin) {
                throw new Exception("You are not allowed to sync LDAP users.");
            }

            try {
                $pdo = db();
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO ldap_users (
                        uid,
                        cn,
                        mail,
                        employee_number,
                        uid_number,
                        gid_number,
                        groups_text,
                        dn,
                        ldap_password
                    ) VALUES (
                        :uid,
                        :cn,
                        :mail,
                        :employee_number,
                        :uid_number,
                        :gid_number,
                        :groups_text,
                        :dn,
                        :ldap_password
                    )
                    ON DUPLICATE KEY UPDATE
                        cn             = VALUES(cn),
                        mail           = VALUES(mail),
                        employee_number= VALUES(employee_number),
                        uid_number     = VALUES(uid_number),
                        gid_number     = VALUES(gid_number),
                        groups_text    = VALUES(groups_text),
                        dn             = VALUES(dn),
                        ldap_password  = VALUES(ldap_password)
                ");

                $uids = [];

                foreach ($users as $u) {
                    $uids[] = $u['uid'];

                    $stmt->execute([
                        ':uid'             => $u['uid'],
                        ':cn'              => $u['cn'],
                        ':mail'            => $u['mail'] !== '' ? $u['mail'] : null,
                        ':employee_number' => $u['employeeNumber'] !== '' ? $u['employeeNumber'] : null,
                        ':uid_number'      => $u['uidNumber'] !== null ? (int)$u['uidNumber'] : null,
                        ':gid_number'      => $u['gidNumber'] !== null ? (int)$u['gidNumber'] : null,
                        ':groups_text'     => $u['groups'] !== '' ? $u['groups'] : null,
                        ':dn'              => $u['dn'],
                        ':ldap_password'   => $u['passwordHash'] !== '' ? $u['passwordHash'] : null,
                    ]);
                }

                if (!empty($uids)) {
                    $placeholders = implode(',', array_fill(0, count($uids), '?'));
                    $del = $pdo->prepare("DELETE FROM ldap_users WHERE uid NOT IN ($placeholders)");
                    $del->execute($uids);
                }

                $pdo->commit();

                header('Location: ' . $_SERVER['PHP_SELF'] . '?synced=1');
                exit;

            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new Exception("Error syncing LDAP users to MariaDB: " . $e->getMessage());
            }
        }

        // A partir de aquí, acciones normales de CRUD
        $uid            = trim((string)($_POST['uid'] ?? ''));
        $cn             = trim((string)($_POST['cn'] ?? ''));
        $email          = trim((string)($_POST['email'] ?? ''));
        $employeeNumber = trim((string)($_POST['employeeNumber'] ?? ''));

        // grupos múltiples
        $selected_groups = [];
        if (isset($_POST['groups']) && is_array($_POST['groups'])) {
            foreach ($_POST['groups'] as $gcn) {
                $gcn = trim((string)$gcn);
                if ($gcn !== '') {
                    $selected_groups[] = $gcn;
                }
            }
        }

        $password       = (string)($_POST['password'] ?? '');
        $old_dn         = (string)($_POST['old_dn'] ?? '');
        $old_cn         = trim((string)($_POST['old_cn'] ?? ''));
        $old_uid        = trim((string)($_POST['old_uid'] ?? ''));

        /* ========= CREATE (admins only) ========= */
        if ($action === 'create') {
            if (!$isAdmin) {
                throw new Exception("You are not allowed to create LDAP users.");
            }

            if ($uid === '' || $cn === '') {
                throw new Exception("UID and Username (cn) are required.");
            }

            if (empty($selected_groups)) {
                throw new Exception("At least one group is required.");
            }

            // Primer grupo elegido será el primary group (gidNumber)
            $primary_group_cn = $selected_groups[0];

            if (!isset($groupsByCn[$primary_group_cn])) {
                throw new Exception("Selected primary group does not exist.");
            }
            $gidNumber = (int)$groupsByCn[$primary_group_cn];

            $newUidNumber = $maxUidNumber + 1;
            if ($newUidNumber < 1000) {
                $newUidNumber = 1000;
            }

            $safeCn = escape_dn_value($cn);
            $dn     = "cn={$safeCn},{$base_users}";

            $entry = [
                'objectClass' => [
                    'top',
                    'person',
                    'organizationalPerson',
                    'inetOrgPerson',
                    'posixAccount',
                    'shadowAccount'
                ],
                'cn'            => $cn,
                'sn'            => $cn,
                'uid'           => $uid,
                'mail'          => $email,
                'uidNumber'     => (string)$newUidNumber,
                'gidNumber'     => (string)$gidNumber,
                'homeDirectory' => "/home/{$uid}",
                'loginShell'    => "/bin/bash",
            ];

            if ($employeeNumber !== '') {
                $entry['employeeNumber'] = $employeeNumber;
            }

            if ($password !== '') {
                $entry['userPassword'] = ldap_hash_md5($password);
            }

            if (!@ldap_add($conn, $dn, $entry)) {
                throw new Exception("Error creating LDAP user: " . ldap_error($conn));
            }

            // Sincronizar memberships en posixGroup
            ldap_sync_user_group_memberships($conn, $base_groups, $uid, $selected_groups);

            header('Location: ' . $_SERVER['PHP_SELF'] . '?created=1');
            exit;
        }

        /* ========= UPDATE ========= */
        if ($action === 'update') {

            if ($old_uid === '') {
                throw new Exception("Missing UID for update.");
            }

            // UID NO SE PUEDE CAMBIAR
            if ($uid !== '' && $uid !== $old_uid) {
                throw new Exception("UID cannot be changed.");
            }

            $effectiveUid = $old_uid;

            if (!$isAdmin && $effectiveUid !== $uid_ldap) {
                throw new Exception("You are not allowed to edit this LDAP user.");
            }

            if ($old_dn === '') {
                throw new Exception("Missing LDAP DN for update.");
            }

            // Solo al admin se le exige grupos
            if ($isAdmin && empty($selected_groups)) {
                throw new Exception("At least one group is required.");
            }

            $currentDn = $old_dn;

            // 1) CN changed => rename
            if ($cn !== '' && $cn !== $old_cn) {
                $safeCn = escape_dn_value($cn);
                $newRdn = "cn={$safeCn}";

                if (!@ldap_rename($conn, $old_dn, $newRdn, null, true)) {
                    throw new Exception("Error renaming LDAP entry: " . ldap_error($conn));
                }

                $parts     = explode(',', $old_dn, 2);
                $parent    = $parts[1] ?? '';
                $currentDn = $newRdn . ',' . $parent;
            }

            // 2) Otros atributos (mail, employeeNumber, gidNumber, password)
            $mods = [];

            // mail (permitimos borrarlo)
            if ($email !== '') {
                $mods['mail'] = $email;
            } else {
                $mods['mail'] = [];
            }

            // employeeNumber (DNI)
            if ($employeeNumber !== '') {
                $mods['employeeNumber'] = $employeeNumber;
            } else {
                $mods['employeeNumber'] = [];
            }

            // primary group = primer grupo de la lista (solo admin puede cambiarlo)
            if ($isAdmin) {
                $primary_group_cn = $selected_groups[0];
                if (!isset($groupsByCn[$primary_group_cn])) {
                    throw new Exception("Selected primary group does not exist.");
                }
                $mods['gidNumber'] = (string)$groupsByCn[$primary_group_cn];
            }

            // password (propio usuario también puede cambiar)
            if ($password !== '') {
                $mods['userPassword'] = ldap_hash_md5($password);
            }

            if (!empty($mods)) {
                if (!@ldap_modify($conn, $currentDn, $mods)) {
                    throw new Exception("Error updating LDAP user: " . ldap_error($conn));
                }
            }

            // Sincronizar memberships en posixGroup SOLO si es admin
            if ($isAdmin) {
                ldap_sync_user_group_memberships($conn, $base_groups, $effectiveUid, $selected_groups);
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=1');
            exit;
        }

        /* ========= DELETE (admins only) ========= */
        if ($action === 'delete') {
            if (!$isAdmin) {
                throw new Exception("You are not allowed to delete LDAP users.");
            }
            if ($old_dn === '') {
                throw new Exception("Missing LDAP DN for delete.");
            }

            if (!@ldap_delete($conn, $old_dn)) {
                throw new Exception("Error deleting LDAP user: " . ldap_error($conn));
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1');
            exit;
        }
    }

    // Mensajes tras redirect
    if (isset($_GET['created'])) {
        $panelMessage = "LDAP user created successfully.";
    } elseif (isset($_GET['updated'])) {
        $panelMessage = "LDAP user updated successfully.";
    } elseif (isset($_GET['deleted'])) {
        $panelMessage = "LDAP user deleted successfully.";
    } elseif (isset($_GET['synced'])) {
        $panelMessage = "LDAP users synced to MariaDB successfully.";
    }

    // Recargar usuarios después de posibles cambios
    [$users, $maxUidNumber] =
        ldap_load_users($conn, $base_users, $groupsByGid, $groupsByMemberUid, $isAdmin, $uid_ldap);

} catch (Exception $e) {
    $panelError = $e->getMessage();
}

/* =========================================
   USER TO EDIT (GET ?edit=uid)
========================================= */
$editUser = null;
if (isset($_GET['edit'])) {
    $editUid = trim((string)$_GET['edit']);
    foreach ($users as $u) {
        if ($u['uid'] === $editUid) {
            if ($isAdmin || $u['uid'] === $uid_ldap) {
                $editUser = $u;
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ChorizoSQL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Global CSS -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

    <style>
        .panel-section {
            width: 100%;
            padding: 7rem 5% 4rem;
            display: flex;
            justify-content: center;
            align-items: stretch;
            position: relative;
            z-index: 1;
            flex: 1 0 auto;
        }

        .panel-container {
            width: 100%;
            max-width: 1500px;
            background: rgba(26, 26, 46, 0.95);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 26px 60px rgba(0,0,0,0.7);
            padding: 2.8rem 3.2rem;
        }

        .panel-header {
            margin-bottom: 1.2rem;
        }

        .panel-title {
            font-size: 2rem;
            color: var(--light);
            margin-bottom: 0.4rem;
        }

        .panel-subtitle {
            font-size: 0.95rem;
            color: rgba(245,245,247,0.85);
        }

        .panel-alert {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .panel-alert-error {
            border: 1px solid #ff6b81;
            background: rgba(255,107,129,0.16);
            color: #ffd7df;
        }

        .panel-alert-success {
            border: 1px solid #4caf50;
            background: rgba(76,175,80,0.16);
            color: #c8ffd0;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr);
            gap: 2.4rem;
            align-items: flex-start;
        }

        .panel-table-wrapper {
            overflow-x: auto;
        }

        .panel-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .panel-table thead {
            background: rgba(255,255,255,0.04);
        }

        .panel-table th,
        .panel-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-align: left;
            color: rgba(245,245,247,0.94);
        }

        .panel-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            color: rgba(245,245,247,0.7);
        }

        .panel-table tr:hover {
            background: rgba(255,255,255,0.04);
        }

        .text-end {
            text-align: right;
        }

        .panel-actions {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn-small {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.38rem 0.8rem;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.3);
            background: transparent;
            color: var(--light);
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-small-primary {
            border-color: var(--primary);
            background: var(--primary);
        }

        .btn-small-primary:hover {
            background: var(--secondary);
        }

        .btn-small-danger {
            border-color: #ff6b81;
            color: #ffdde4;
        }

        .btn-small-danger:hover {
            background: #ff6b81;
            color: #1a1a2e;
        }

        .panel-form {
            background: rgba(10,10,25,0.85);
            border-radius: 18px;
            padding: 1.6rem 1.8rem;
            border: 1px solid rgba(255,255,255,0.09);
        }

        .panel-form h3 {
            font-size: 1.3rem;
            margin-bottom: 1.1rem;
            color: var(--light);
        }

        .panel-form .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }

        .panel-form label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--light);
        }

        .panel-form input,
        .panel-form select {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border-radius: 10px;
            border: 2px solid rgba(255,255,255,0.18);
            background-color: rgba(255,255,255,0.04);
            color: var(--light);
            font-size: 0.9rem;
            transition: var(--transition);
            appearance: none;
        }

        .panel-form select {
            cursor: pointer;
        }

        .panel-form option {
            background-color: #1a1a2e;
            color: var(--light);
        }

        .panel-form input:focus,
        .panel-form select:focus {
            outline: none;
            border-color: var(--primary);
            background-color: rgba(255,255,255,0.09);
        }

        .panel-form small {
            display: block;
            margin-top: 0.2rem;
            font-size: 0.76rem;
            color: rgba(245,245,247,0.7);
        }

        .panel-form .cc-btn-full {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 12px 28px rgba(0,0,0,0.45);
            transition: var(--transition);
        }

        .panel-form .cc-btn-primary {
            background-color: var(--primary);
            color: var(--light);
        }

        .panel-form .cc-btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }

        .panel-empty {
            text-align: center;
            padding: 1rem 0.5rem;
            color: rgba(245,245,247,0.7);
        }

        @media (max-width: 992px) {
            .panel-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .panel-container {
                padding: 2.2rem 1.6rem;
            }
        }
    </style>
</head>
<body>
<div id="particles-js"></div>

<header id="header">
    <?php include '../inc/header.php'; ?>
</header>

<section class="hero" id="panel">
    <div class="panel-section">
        <div class="panel-container">

            <div class="panel-header">
                <h1 class="panel-title">
                    User administration
                    <small style="font-size:0.9rem;color:rgba(245,245,247,0.75);">
                        (<?php echo $isAdmin ? 'admin' : 'limited user'; ?> · <?php echo $displayUser; ?>)
                    </small>
                </h1>
                <p class="panel-subtitle">
                    <?php if ($isAdmin): ?>
                        As an administrator, you can manage all LDAP users in the system.
                    <?php else: ?>
                        You can see and update only your own LDAP account details.
                    <?php endif; ?>
                </p>

                <?php if ($isAdmin): ?>
                    <form method="post" style="margin-top:0.8rem;">
                        <input type="hidden" name="csrf_token"
                               value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="sync_db">
                        <button type="submit" class="btn-small btn-small-primary">
                            <i class="fas fa-arrows-rotate"></i>&nbsp;Sync LDAP → DB
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($panelError): ?>
                <div class="panel-alert panel-alert-error">
                    <i class="fas fa-triangle-exclamation"></i>
                    <?php echo htmlspecialchars($panelError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php elseif ($panelMessage): ?>
                <div class="panel-alert panel-alert-success">
                    <i class="fas fa-circle-check"></i>
                    <?php echo htmlspecialchars($panelMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="panel-grid">
                <!-- LEFT: TABLE -->
                <div class="panel-table-wrapper">
                    <table class="panel-table">
                        <thead>
                        <tr>
                            <th>UID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>DNI</th>
                            <th>Group(s)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($users): ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['uid']); ?></td>
                                    <td><?php echo htmlspecialchars($u['cn']); ?></td>
                                    <td><?php echo htmlspecialchars($u['mail']); ?></td>
                                    <td><?php echo htmlspecialchars($u['employeeNumber']); ?></td>
                                    <td><?php echo htmlspecialchars($u['groups']); ?></td>
                                    <td class="text-end">
                                        <div class="panel-actions">
                                            <?php if ($isAdmin || $u['uid'] === $uid_ldap): ?>
                                                <a class="btn-small btn-small-primary"
                                                   href="?edit=<?php echo urlencode($u['uid']); ?>">
                                                    <i class="fas fa-pen"></i>&nbsp;Edit
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($isAdmin): ?>
                                                <form method="post" style="display:inline;"
                                                      onsubmit="return confirm('Delete this LDAP user?');">
                                                    <input type="hidden" name="csrf_token"
                                                           value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="old_dn"
                                                           value="<?php echo htmlspecialchars($u['dn']); ?>">
                                                    <button type="submit" class="btn-small btn-small-danger">
                                                        <i class="fas fa-trash"></i>&nbsp;Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td class="panel-empty" colspan="6">
                                    No LDAP users found for your view.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- RIGHT: FORM -->
                <div>
                    <div class="panel-form">
                        <?php if ($editUser): ?>
                            <h3>Edit LDAP user</h3>
                            <form method="post" autocomplete="off">
                                <input type="hidden" name="csrf_token"
                                       value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="old_dn"
                                       value="<?php echo htmlspecialchars($editUser['dn']); ?>">
                                <input type="hidden" name="old_cn"
                                       value="<?php echo htmlspecialchars($editUser['cn']); ?>">
                                <input type="hidden" name="old_uid"
                                       value="<?php echo htmlspecialchars($editUser['uid']); ?>">

                                <div class="form-group">
                                    <label for="uid">UID</label>
                                    <input type="text" id="uid" name="uid"
                                           value="<?php echo htmlspecialchars($editUser['uid']); ?>"
                                           readonly>
                                    <small>UID cannot be changed.</small>
                                </div>

                                <div class="form-group">
                                    <label for="cn">Username (cn)</label>
                                    <input type="text" id="cn" name="cn"
                                           value="<?php echo htmlspecialchars($editUser['cn']); ?>"
                                           required>
                                </div>

                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email"
                                           value="<?php echo htmlspecialchars($editUser['mail']); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="employeeNumber">Employee Nº (DNI)</label>
                                    <input type="text" id="employeeNumber" name="employeeNumber"
                                           value="<?php echo htmlspecialchars($editUser['employeeNumber']); ?>"
                                           placeholder="e.g. 22229999B">
                                </div>

                                <?php
                                // grupos actuales del usuario como array
                                $currentGroups = [];
                                if (!empty($editUser['groups'])) {
                                    $currentGroups = array_map('trim', explode(',', $editUser['groups']));
                                }
                                ?>

                                <?php if ($isAdmin): ?>
                                    <div class="form-group">
                                        <label for="groups">Groups (multi-select)</label>
                                        <select id="groups" name="groups[]" multiple size="5">
                                            <?php foreach ($ldapGroups as $g): ?>
                                                <?php $cnOpt = $g['cn']; ?>
                                                <option value="<?php echo htmlspecialchars($cnOpt); ?>"
                                                    <?php echo in_array($cnOpt, $currentGroups, true) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cnOpt); ?>
                                                    <?php if ($g['gid'] !== null): ?>
                                                        (gid: <?php echo (int)$g['gid']; ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small>First selected group will be the primary group (gidNumber).</small>
                                    </div>
                                <?php else: ?>
                                    <div class="form-group">
                                        <label>Groups</label>
                                        <input type="text"
                                               value="<?php echo htmlspecialchars($editUser['groups']); ?>"
                                               readonly>
                                        <small>You cannot change groups. Contact an administrator.</small>
                                    </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label for="password">New password (leave empty to keep)</label>
                                    <input type="password" id="password" name="password"
                                           placeholder="Set a new password">
                                </div>

                                <button type="submit" class="cc-btn-full cc-btn-primary">
                                    <i class="fas fa-save"></i>
                                    Save changes
                                </button>
                            </form>

                        <?php elseif ($isAdmin): ?>
                            <h3>Create LDAP user</h3>
                            <form method="post" autocomplete="off">
                                <input type="hidden" name="csrf_token"
                                       value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="create">

                                <div class="form-group">
                                    <label for="uid_create">UID</label>
                                    <input type="text" id="uid_create" name="uid"
                                           placeholder="e.g. jsmith"
                                           required>
                                </div>

                                <div class="form-group">
                                    <label for="cn_create">Username (cn)</label>
                                    <input type="text" id="cn_create" name="cn"
                                           placeholder="John Smith"
                                           required>
                                </div>

                                <div class="form-group">
                                    <label for="email_create">Email</label>
                                    <input type="email" id="email_create" name="email"
                                           placeholder="user@example.com">
                                </div>

                                <div class="form-group">
                                    <label for="employeeNumber_create">Employee Nº (DNI)</label>
                                    <input type="text" id="employeeNumber_create" name="employeeNumber"
                                           placeholder="e.g. 22229999B">
                                </div>

                                <div class="form-group">
                                    <label for="groups_create">Groups (multi-select)</label>
                                    <select id="groups_create" name="groups[]" multiple size="5" required>
                                        <?php foreach ($ldapGroups as $g): ?>
                                            <option value="<?php echo htmlspecialchars($g['cn']); ?>">
                                                <?php echo htmlspecialchars($g['cn']); ?>
                                                <?php if ($g['gid'] !== null): ?>
                                                    (gid: <?php echo (int)$g['gid']; ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>First selected group will be the primary group (gidNumber).</small>
                                </div>

                                <div class="form-group">
                                    <label for="password_create">Initial password</label>
                                    <input type="password" id="password_create" name="password"
                                           placeholder="Set an initial password">
                                </div>

                                <button type="submit" class="cc-btn-full cc-btn-primary">
                                    <i class="fas fa-user-plus"></i>
                                    Create LDAP user
                                </button>
                            </form>

                        <?php else: ?>
                            <h3>Your LDAP account</h3>
                            <p style="font-size:0.9rem;color:rgba(245,245,247,0.8);">
                                Select your row in the table and click <strong>Edit</strong> to update your details.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<footer>
    <?php include '../inc/footer.php'; ?>
</footer>

<div class="scroll-top"><i class="fas fa-arrow-up"></i></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/particles.js/2.0.0/particles.min.js"></script>
<script>
    const hamburger = document.querySelector('.hamburger');
    const navLinks  = document.querySelector('.nav-links');
    const links     = document.querySelectorAll('.nav-links li');

    hamburger?.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        hamburger.classList.toggle('active');
        links.forEach((link, index) => {
            link.style.animation = link.style.animation
                ? ''
                : `navLinkFade 0.5s ease forwards ${index / 7 + 0.3}s`;
        });
    });

    links.forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('active');
            hamburger?.classList.remove('active');
            links.forEach(l => l.style.animation = '');
        });
    });

    const scrollTopBtn = document.querySelector('.scroll-top');
    window.addEventListener('scroll', () => {
        document.getElementById('header').classList.toggle('scrolled', window.scrollY > 100);
        scrollTopBtn.classList.toggle('active', window.pageYOffset > 300);
    });

    scrollTopBtn?.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    particlesJS("particles-js", {
        "particles": {
            "number": { "value": 80, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": "#6c63ff" },
            "shape": { "type": "circle" },
            "opacity": { "value": 0.5 },
            "size": { "value": 3, "random": true },
            "line_linked": { "enable": true, "distance": 150, "color": "#6c63ff", "opacity": 0.4, "width": 1 },
            "move": { "enable": true, "speed": 2 }
        },
        "interactivity": {
            "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": true, "mode": "push" } },
            "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 1 } } }
        },
        "retina_detect": true
    });
</script>
</body>
</html>
