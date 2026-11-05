#!/bin/sh
# Arranque de JasperStarter 3.6.2 valido con Java 9+: los drivers JDBC van en el classpath, no en jdbc/
# (el original hace "java -jar", y con Java 9+ la carga de jdbc/ falla con ClassCastException)
HOME_FOLDER="$(dirname "$(readlink -f "$0")")/.."
exec java -cp "$HOME_FOLDER/lib/jasperstarter.jar:$HOME_FOLDER/drivers/*" de.cenote.jasperstarter.App "$@"
