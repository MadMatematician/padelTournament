-- Script da eseguire con psql come superuser (es. -U postgres).
-- Esempio: psql -U postgres -f "C:\...\create_db_and_user.sql"
-- Oppure dentro psql: \i 'C:\...\create_db_and_user.sql'
--
-- Preferibile: passare i valori dal file .env di Laravel con -v.
-- Esempi (Bash, dalla cartella del progetto Laravel):
--   DB_DATABASE=$(grep -E '^DB_DATABASE=' .env | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
--   DB_USERNAME=$(grep -E '^DB_USERNAME=' .env | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
--   DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d'=' -f2- | tr -d '"' | tr -d '\r')
--   psql -U postgres -v dbname="$DB_DATABASE" -v dbuser="$DB_USERNAME" -v dbpass="$DB_PASSWORD" -f "C:\path\to\create_db_and_user.sql"
--
-- Esempio PowerShell (Windows, dalla cartella del progetto Laravel):
--   $envfile = Get-Content .\.env
--   $db = ($envfile -match '^DB_DATABASE=' | ForEach-Object { $_ -replace '^DB_DATABASE=','' }) -replace '"',''
--   $user = ($envfile -match '^DB_USERNAME=' | ForEach-Object { $_ -replace '^DB_USERNAME=','' }) -replace '"',''
--   $pass = ($envfile -match '^DB_PASSWORD=' | ForEach-Object { $_ -replace '^DB_PASSWORD=','' }) -replace '"',''
--   psql -U postgres -v dbname="$db" -v dbuser="$user" -v dbpass="$pass" -f "C:\path\to\create_db_and_user.sql"
--
-- Nota: lo script DEVE essere eseguito da un superuser (es. postgres) perché crea ruoli/database.

-- Sostituisco le impostazioni statiche con controlli che usano variabili psql (se definite)
\if !defined(dbname)
\set dbname 'giustizia_db'
\endif

\if !defined(dbuser)
\set dbuser 'giustizia_user'
\endif

\if !defined(dbpass)
\set dbpass 'ChangeMe#StrongPassword'
\endif

-- 1) Creazione ruolo (se non esiste) con password
DO
$$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = :'dbuser') THEN
    EXECUTE format(
      'CREATE ROLE %I WITH LOGIN PASSWORD %L NOINHERIT NOCREATEDB NOCREATEROLE',
      :'dbuser', :'dbpass'
    );
  END IF;
END
$$;

-- 2) Creazione database (se non esiste) e assegnazione owner
DO
$$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_database WHERE datname = :'dbname') THEN
    EXECUTE format(
      'CREATE DATABASE %I OWNER %I ENCODING ''UTF8'' TEMPLATE template0',
      :'dbname', :'dbuser'
    );
  END IF;
END
$$;

-- 3) Assicurarsi che il ruolo possa connettersi al database
GRANT CONNECT ON DATABASE :"dbname" TO :"dbuser";

-- 4) Connettersi al database appena creato per assegnare permessi su schema public
\connect :'dbname'

-- Impostare il proprietario dello schema public
ALTER SCHEMA public OWNER TO :"dbuser";

-- Grants sullo schema e sugli oggetti esistenti
GRANT ALL ON SCHEMA public TO :"dbuser";
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO :"dbuser";
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO :"dbuser";
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO :"dbuser";

-- NOTA: le "default privileges" per oggetti futuri devono essere create dall'account
-- che eseguirà le migrazioni (se diverso, eseguirle come quel ruolo). Esempio se le
-- migrazioni saranno eseguite come il nuovo utente, connettersi come quell'utente e poi:
-- ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO <dbuser>;
-- Se vuoi provare a impostarle qui per il nuovo owner e puoi eseguire questo script
-- come superuser AND poi eseguire anche il seguente comando come il nuovo owner, fallo manualmente.

-- Fine script
