# JasperStarter en Docker para Windows

Ejecuta [JasperStarter](https://jasperstarter.cenote.de/) 3.6.2 en un contenedor Docker (Java 17)
y lo expone en Windows como un comando `jasperstarter` normal. Sirve para generar y probar reportes
JasperReports (`.jrxml` / `.jasper`) en local sin instalar Java ni JasperStarter en la máquina, y
para cualquier aplicación que llame a `jasperstarter` desde el PATH.

Repositorio: https://github.com/nestor1610/jasperstarter-local

## Cómo funciona

`bin\jasperstarter.cmd` es el `jasperstarter` del PATH. Llama a `bin\jasperstarter-docker.php`, que
traduce los argumentos (rutas de Windows, host de la base de datos) y ejecuta
`docker exec <contenedor> jasperstarter ...`. La salida y el código de salida son los del contenedor.

| Archivo | Qué es |
| --- | --- |
| `build\Dockerfile` | Imagen `jasperstarter:3.6.2` (Temurin 17 JRE, fuentes DejaVu y Liberation) |
| `build\jasperstarter.sh` | Arranque con los drivers JDBC en el classpath (ver «Problemas conocidos») |
| `build\*.zip`, `build\*.jar` | JasperStarter 3.6.2 y los drivers JDBC. **No se versionan** (ver «Instalación») |
| `bin\jasperstarter.cmd` | El `jasperstarter` del PATH. Llama a `php -n bin\jasperstarter-docker.php` |
| `bin\jasperstarter-docker.php` | Traduce rutas y conexión y hace `docker exec` |
| `prueba-conexion.jrxml` | Reporte mínimo: muestra la base de datos, la versión de MySQL y el usuario conectado |

## Requisitos

- Docker Desktop.
- PHP 7.4 o superior en el PATH (solo para el puente; no necesita extensiones ni `php.ini`).

## Instalación

1. **Binarios.** Descárgalos en `build\`:
   - `jasperstarter-3.6.2-bin.zip`: https://sourceforge.net/projects/jasperstarter/files/
     (el Dockerfile comprueba su sha1, `e35c6cb0761c8f3e30afebe58049112641371c01`).
   - Uno o más drivers JDBC (`.jar`). Todos los `.jar` de `build\` se copian a la imagen. Para MySQL:
     https://repo1.maven.org/maven2/com/mysql/mysql-connector-j/8.4.0/mysql-connector-j-8.4.0.jar

2. **Imagen y contenedor.** Monta en el contenedor las carpetas donde están tus reportes y donde
   quieras la salida (aquí, `C:\reportes` como `/reportes`):

   ```powershell
   cd <clon>\build
   docker build -t jasperstarter:3.6.2 .
   docker rm -f jasperstarter
   docker run -d --name jasperstarter --restart unless-stopped `
     -v C:\reportes:/reportes `
     jasperstarter:3.6.2
   ```

3. **Variables de entorno y PATH** (de usuario). `JASPERSTARTER_MOUNTS` tiene que repetir los mismos
   montajes del `docker run`:

   ```powershell
   [Environment]::SetEnvironmentVariable('JASPERSTARTER_MOUNTS', 'C:\reportes=/reportes', 'User')
   $path = [Environment]::GetEnvironmentVariable('Path', 'User')
   [Environment]::SetEnvironmentVariable('Path', "$path;<clon>\bin", 'User')
   ```

   Las consolas y editores ya abiertos (VS Code incluido) tienen que reiniciarse para verlas.

4. **Comprobación.** Desde una consola nueva: `jasperstarter --version`.

## Uso

Igual que JasperStarter, con rutas de Windows que estén dentro de un montaje:

```powershell
jasperstarter process C:\reportes\prueba-conexion.jrxml -o C:\reportes\salida -f pdf `
  -t mysql -H 127.0.0.1 --db-port 3306 -n mi_base -u mi_usuario -p mi_clave
```

## Configuración

| Variable | Por defecto | Qué hace |
| --- | --- | --- |
| `JASPERSTARTER_CONTAINER` | `jasperstarter` | Contenedor al que se reenvían las llamadas |
| `JASPERSTARTER_MOUNTS` | (vacía) | Montajes `C:\ruta=/ruta;D:\otra=/otra`. Sin ella no se traduce ninguna ruta |
| `JASPERSTARTER_MYSQL_PARAMS` | `sslMode=REQUIRED` | Parámetros de la URL JDBC cuando se usa `-t mysql` |
| `JASPERSTARTER_DEBUG` | (vacía) | Con cualquier valor, imprime el comando final por stderr |

## Qué traduce el puente

- Rutas que empiezan por una carpeta de `JASPERSTARTER_MOUNTS` → su ruta dentro del contenedor.
  También dentro de los `-P clave=ruta` (sub reportes, imágenes...). Las demás rutas se pasan tal cual.
- `-H 127.0.0.1` o `localhost` → `host.docker.internal` (desde el contenedor, 127.0.0.1 es el propio
  contenedor).
- `-t mysql -H … --db-port … -n …` → `-t generic --db-driver com.mysql.cj.jdbc.Driver
  --db-url jdbc:mysql://host:puerto/base?<JASPERSTARTER_MYSQL_PARAMS>`.

## Problemas conocidos de JasperStarter 3.6.2

1. **No carga los drivers de `jdbc/` con Java 9 o superior.** Convierte el classloader del sistema
   en `URLClassLoader` y falla con `ClassCastException`. Solución: dejar `jdbc/` vacía y arrancar con
   `java -cp lib/jasperstarter.jar:<drivers> de.cenote.jasperstarter.App`, que es lo que hace
   `build\jasperstarter.sh` con los `.jar` de `drivers/`.
2. **Con `-t mysql` fija `useSSL=false`.** Sin TLS, Connector/J 8 no puede autenticar usuarios
   `caching_sha2_password` (el método por defecto de MySQL 8) y falla con «Public Key Retrieval is
   not allowed». Solución: usar `-t generic` con `--db-url ...?sslMode=REQUIRED` (o `VERIFY_CA` con
   el certificado del servidor), que es lo que hace el puente. Si tu servidor no tiene TLS, cambia
   `JASPERSTARTER_MYSQL_PARAMS`, por ejemplo a `allowPublicKeyRetrieval=true&sslMode=DISABLED`.
