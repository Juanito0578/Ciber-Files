#!/usr/bin/env bash
set -e

echo "==> Iniciando configuración de OpenLDAP..."

# --- Comprobación de variables requeridas ---
: "${LDAP_DOMAIN:?Debe definirse LDAP_DOMAIN}"
: "${LDAP_ORGANISATION:?Debe definirse LDAP_ORGANISATION}"
: "${LDAP_ADMIN_PASSWORD:?Debe definirse LDAP_ADMIN_PASSWORD}"

# --- Derivar suffix y DN admin ---
SUFFIX=$(echo "$LDAP_DOMAIN" | sed 's/^/dc=/' | sed 's/\./,dc=/g')
ROOTDN="cn=admin,${SUFFIX}"
ROOTPW_HASH=$(slappasswd -s "$LDAP_ADMIN_PASSWORD")

export SUFFIX ROOTDN ROOTPW_HASH

# --- Renderizar slapd.conf ---
echo "==> Generando /etc/ldap/slapd.conf desde plantilla..."
envsubst < /etc/ldap/slapd.conf.template > /etc/ldap/slapd.conf

# --- Inicializar base de datos si está vacía ---
if [ ! -f /var/lib/ldap/data.mdb ]; then
  echo "==> Inicializando base LDAP en /var/lib/ldap ..."
  cat > /tmp/base.ldif <<EOF
dn: ${SUFFIX}
objectClass: top
objectClass: dcObject
objectClass: organization
o: ${LDAP_ORGANISATION}
dc: $(echo "$LDAP_DOMAIN" | cut -d. -f1)

dn: ${ROOTDN}
objectClass: simpleSecurityObject
objectClass: organizationalRole
cn: admin
description: Directory Manager
userPassword: ${ROOTPW_HASH}

dn: ou=people,${SUFFIX}
objectClass: organizationalUnit
ou: people

dn: ou=groups,${SUFFIX}
objectClass: organizationalUnit
ou: groups
EOF

  if [ "${LDAP_READONLY_USER:-false}" = "true" ]; then
    echo "==> Añadiendo usuario readonly..."
    ROPASS_HASH=$(slappasswd -s "$LDAP_READONLY_USER_PASSWORD")
    cat >> /tmp/base.ldif <<EOF

dn: uid=${LDAP_READONLY_USER_USERNAME},ou=people,${SUFFIX}
objectClass: inetOrgPerson
cn: ${LDAP_READONLY_USER_USERNAME}
sn: ${LDAP_READONLY_USER_USERNAME}
uid: ${LDAP_READONLY_USER_USERNAME}
mail: ${LDAP_READONLY_USER_USERNAME}@${LDAP_DOMAIN}
userPassword: ${ROPASS_HASH}
EOF
  fi

  echo "==> Importando datos iniciales con slapadd..."
  runuser -u openldap -- slapadd -f /etc/ldap/slapd.conf -l /tmp/base.ldif

  chown -R openldap:openldap /var/lib/ldap
  echo "==> Base LDAP creada correctamente."
else
  echo "==> Base LDAP existente, no se recrea."
fi

echo "==> Arrancando slapd..."
exec slapd -d stats -f /etc/ldap/slapd.conf -h "ldap:/// ldaps:///"
