#!/usr/bin/env bash
set -euo pipefail

# Uso:
#   ./create_db_from_env.sh [path-to-env]
# Esempio (posizionarsi nella cartella Laravel):
#   ./scripts/create_db_from_env.sh .env
#
# Output (nella stessa cartella dello script):
#   create_db_and_user.generated.sql   crea utente e database del .env e assegna i privilegi
#   drop_db_and_user.generated.sql     elimina database e utente del .env
#
# I file generati contengono la password in chiaro: non vanno versionati
# (vedi /scripts/*.generated.sql in .gitignore).

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${1:-.env}"
TEMPLATE="${SCRIPT_DIR}/create_db_and_user.tpl.sql"
OUT_FILE="${SCRIPT_DIR}/create_db_and_user.generated.sql"
DROP_TEMPLATE="${SCRIPT_DIR}/drop_db_and_user.tpl.sql"
DROP_OUT_FILE="${SCRIPT_DIR}/drop_db_and_user.generated.sql"

if [ ! -f "$TEMPLATE" ]; then
  echo "Template non trovato: $TEMPLATE" >&2
  exit 2
fi

if [ ! -f "$ENV_FILE" ]; then
  echo ".env non trovato: $ENV_FILE" >&2
  exit 3
fi

# Legge una variabile dal .env come fa Laravel: ultima occorrenza, tollera spazi iniziali,
# "export", CRLF, virgolette singole o doppie e commenti in coda ai valori senza virgolette.
# Se la chiave manca restituisce stringa vuota (senza far uscire lo script per via di pipefail).
get_env_value() {
  local key="$1"
  local file="$2"
  local line value

  line="$(grep -E "^[[:space:]]*(export[[:space:]]+)?${key}[[:space:]]*=" "$file" | tail -n1 || true)"
  if [ -z "$line" ]; then
    return 0
  fi

  value="$(printf '%s' "${line#*=}" | sed -e 's/\r$//' -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"

  case "$value" in
    \"*\") value="${value#\"}"; value="${value%\"}" ;;
    \'*\') value="${value#\'}"; value="${value%\'}" ;;
    *)     value="$(printf '%s' "$value" | sed -e 's/[[:space:]]\{1,\}#.*$//')" ;;
  esac

  printf '%s' "$value"
}

DB_CONN="$(get_env_value DB_CONNECTION "$ENV_FILE")"
DB_HOST="$(get_env_value DB_HOST "$ENV_FILE")"
DB_PORT="$(get_env_value DB_PORT "$ENV_FILE")"
DB_NAME="$(get_env_value DB_DATABASE "$ENV_FILE")"
DB_USER="$(get_env_value DB_USERNAME "$ENV_FILE")"
DB_PASS="$(get_env_value DB_PASSWORD "$ENV_FILE")"

# Se mancanti, richiedi input all'utente
[ -z "$DB_NAME" ] && read -rp "DB_DATABASE non trovato in $ENV_FILE. Inserisci DB name: " DB_NAME
[ -z "$DB_USER" ] && read -rp "DB_USERNAME non trovato in $ENV_FILE. Inserisci DB user: " DB_USER
if [ -z "$DB_PASS" ]; then
  read -rsp "DB_PASSWORD non trovato in $ENV_FILE. Inserisci DB password (input nascosto): " DB_PASS
  echo
fi

if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ]; then
  echo "DB name e DB user sono obbligatori." >&2
  exit 4
fi

if [ -n "$DB_CONN" ] && [ "$DB_CONN" != "pgsql" ]; then
  echo "ATTENZIONE: DB_CONNECTION=${DB_CONN} in $ENV_FILE. Questi script sono per PostgreSQL" >&2
  echo "            e in Laravel la connessione PostgreSQL si chiama 'pgsql'." >&2
fi

# Prepara un valore per il template: prima l'escape per le righe  \set nome '...'  di psql
# (\ -> \\ e ' -> ''), poi l'escape dei caratteri speciali nel replacement di sed (\, / e &).
escape_for_template() {
  printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g" -e 's/[\\/&]/\\&/g'
}

DB_NAME_ESC="$(escape_for_template "$DB_NAME")"
DB_USER_ESC="$(escape_for_template "$DB_USER")"
DB_PASS_ESC="$(escape_for_template "$DB_PASS")"

# Genera un file sostituendo i placeholder del template
render() {
  sed -e "s/__DB_NAME__/${DB_NAME_ESC}/g" \
      -e "s/__DB_USER__/${DB_USER_ESC}/g" \
      -e "s/__DB_PASS__/${DB_PASS_ESC}/g" \
      "$1" > "$2"
}

render "$TEMPLATE" "$OUT_FILE"
echo "File generato: $OUT_FILE"

if [ -f "$DROP_TEMPLATE" ]; then
  render "$DROP_TEMPLATE" "$DROP_OUT_FILE"
  echo "File generato: $DROP_OUT_FILE  (ELIMINA database e utente: usare con cautela)"
fi

# Stesso server a cui si collegherà Laravel
PSQL_OPTS="-U postgres"
[ -n "$DB_HOST" ] && PSQL_OPTS="$PSQL_OPTS -h $DB_HOST"
[ -n "$DB_PORT" ] && PSQL_OPTS="$PSQL_OPTS -p $DB_PORT"

echo
echo "Database: $DB_NAME   Utente: $DB_USER"
echo
echo "Esempio per eseguire (come superuser postgres):"
echo "  psql $PSQL_OPTS -f \"$OUT_FILE\""
echo
echo "Oppure, dentro psql aperto come superuser:"
echo "  \\i '$OUT_FILE'"
