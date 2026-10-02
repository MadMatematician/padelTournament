-- Template: non eseguire direttamente. Usare create_db_from_env.sh per generare il file finale.
--
-- ATTENZIONE: elimina completamente il database e l'utente indicati nel .env.
-- Esecuzione del file generato, come superuser:
--   psql -U postgres -f drop_db_and_user.generated.sql

\set ON_ERROR_STOP on

\set dbname '__DB_NAME__'
\set dbuser '__DB_USER__'

-- Ci si collega a un database diverso da quello da eliminare
\connect postgres

-- 1) Termina tutte le connessioni attive al database
SELECT pg_terminate_backend(pg_stat_activity.pid)
FROM pg_stat_activity
WHERE pg_stat_activity.datname = :'dbname'
  AND pid <> pg_backend_pid();

-- 2) Elimina il database
DROP DATABASE IF EXISTS :"dbname";

-- 3) Elimina l'utente (opzionale - commentare se vuoi mantenerlo)
DROP ROLE IF EXISTS :"dbuser";

-- Fine template
