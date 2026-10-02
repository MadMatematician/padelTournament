-- Template: non eseguire direttamente. Usare create_db_from_env.sh per generare il file finale.
--
-- Placeholder: __DB_NAME__, __DB_USER__, __DB_PASS__
-- Compaiono SOLO nelle tre righe \set qui sotto: nel resto dello script si usano le variabili psql
-- (:'var' = valore tra apici, :"var" = identificatore tra virgolette), così nomi con maiuscole
-- e password con caratteri speciali vengono quotati correttamente da psql.
--
-- Esecuzione del file generato, come superuser:
--   psql -U postgres -f create_db_and_user.generated.sql

\set ON_ERROR_STOP on

\set dbname '__DB_NAME__'
\set dbuser '__DB_USER__'
\set dbpass '__DB_PASS__'

-- 1) Creazione ruolo (se non esiste) con password
SELECT format('CREATE ROLE %I WITH LOGIN PASSWORD %L NOINHERIT NOCREATEDB NOCREATEROLE', :'dbuser', :'dbpass')
WHERE NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = :'dbuser')\gexec

-- 2) Creazione database (se non esiste) e assegnazione owner
-- Nota: CREATE DATABASE non può essere eseguito dentro un blocco DO/funzione:
-- si costruisce il comando con una SELECT condizionale e lo si esegue con \gexec
SELECT format('CREATE DATABASE %I OWNER %I ENCODING ''UTF8'' TEMPLATE template0', :'dbname', :'dbuser')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'dbname')\gexec

-- 3) Tutti i privilegi sul database (CONNECT, CREATE, TEMPORARY)
GRANT ALL PRIVILEGES ON DATABASE :"dbname" TO :"dbuser";

-- 4) Connettersi al database per assegnare i permessi sullo schema public
\connect :"dbname"

-- Impostare il proprietario dello schema public
ALTER SCHEMA public OWNER TO :"dbuser";

-- Grants sullo schema e sugli oggetti esistenti
GRANT ALL ON SCHEMA public TO :"dbuser";
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO :"dbuser";
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO :"dbuser";
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO :"dbuser";

-- Fine template
