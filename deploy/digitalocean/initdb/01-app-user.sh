#!/bin/sh
# Runs ONCE, only when the fliperp pgdata volume is first initialised.
# Creates the dedicated non-superuser application role and the database it owns.
set -eu
: "${APP_DB_USER:?}"; : "${APP_DB_PASSWORD:?}"; : "${APP_DB_NAME:?}"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  -v app_user="$APP_DB_USER" -v app_password="$APP_DB_PASSWORD" -v app_db="$APP_DB_NAME" <<'SQL'
SELECT format('CREATE ROLE %I WITH LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE', :'app_user', :'app_password')
\gexec
SELECT format('CREATE DATABASE %I OWNER %I', :'app_db', :'app_user')
\gexec
SQL

echo "Flip ERP: application role and database created."
