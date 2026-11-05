@echo off
rem jasperstarter para Windows: ejecuta la llamada dentro del contenedor Docker (ver LEEME.md)
php -n "%~dp0jasperstarter-docker.php" %*
exit /b %ERRORLEVEL%
