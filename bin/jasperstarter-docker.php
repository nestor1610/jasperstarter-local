<?php

/**
 * jasperstarter para Windows: reenvia la llamada al contenedor Docker que tiene JasperStarter.
 *
 * - Las rutas de Windows declaradas en JASPERSTARTER_MOUNTS pasan a su ruta dentro del contenedor,
 *   tambien dentro de los -P clave=ruta (sub reportes, imagenes...).
 * - -H 127.0.0.1 / localhost pasa a host.docker.internal: desde el contenedor, 127.0.0.1 es el
 *   propio contenedor; una base de datos local se alcanza por el host de Docker Desktop.
 * - -t mysql pasa a -t generic con la URL JDBC completa (ver LEEME.md, «Problemas conocidos»).
 *
 * Variables de entorno:
 *   JASPERSTARTER_CONTAINER     nombre del contenedor (por defecto "jasperstarter")
 *   JASPERSTARTER_MOUNTS        carpetas montadas, "C:\ruta=/ruta/contenedor;D:\otra=/otra"
 *   JASPERSTARTER_MYSQL_PARAMS  parametros de la URL JDBC de MySQL (por defecto "sslMode=REQUIRED")
 *   JASPERSTARTER_DEBUG         si tiene valor, imprime el comando final por stderr
 *
 * Compatible con PHP 7.4 (se ejecuta con el php del PATH y sin php.ini).
 */

$container = getenv('JASPERSTARTER_CONTAINER') ?: 'jasperstarter';
$mysql_params = getenv('JASPERSTARTER_MYSQL_PARAMS');
$mysql_params = $mysql_params === false ? 'sslMode=REQUIRED' : $mysql_params;
$args = array_slice($argv, 1);
$translated = [];

// [ruta de Windows con "/" y sin "/" final, ruta en el contenedor sin "/" final]
$mounts = [];

foreach (explode(';', (string) getenv('JASPERSTARTER_MOUNTS')) as $pair) {
	$parts = explode('=', $pair, 2);
	if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') continue;
	$mounts[] = [rtrim(str_replace('\\', '/', trim($parts[0])), '/'), rtrim(trim($parts[1]), '/')];
}

$to_container = function ($arg) use ($mounts) {
	$path = str_replace('\\', '/', $arg);

	foreach ($mounts as [$host, $inside]) {
		$length = strlen($host);
		if (strncasecmp($path, $host, $length) === 0 && (strlen($path) === $length || $path[$length] === '/')) {
			return $inside . substr($path, $length);
		}
	}

	return $arg;
};

for ($i = 0; $i < count($args); $i++) {
	$arg = $args[$i];

	if (in_array($arg, ['-H', '--db-host'], true) && isset($args[$i + 1]) && in_array(strtolower($args[$i + 1]), ['127.0.0.1', 'localhost'], true)) {
		$translated[] = $arg;
		$translated[] = 'host.docker.internal';
		$i++;
		continue;
	}

	// -P clave=C:\...\subreporte: solo se traduce lo que va despues del "="
	if (preg_match('/^([A-Za-z_][\w.]*)=(.*)$/s', $arg, $m)) {
		$translated[] = $m[1] . '=' . $to_container($m[2]);
		continue;
	}

	$translated[] = $to_container($arg);
}

// -t mysql: JasperStarter arma jdbc:mysql://...?useSSL=false, y sin TLS Connector/J 8 no puede autenticar
// usuarios caching_sha2_password (los de MySQL 8). Se pasa a -t generic con la URL completa
$type = array_search('-t', $translated, true);

if ($type !== false && ($translated[$type + 1] ?? null) === 'mysql') {
	$take = function ($flag) use (&$translated) {
		$at = array_search($flag, $translated, true);
		if ($at === false) return null;
		$value = $translated[$at + 1] ?? null;
		array_splice($translated, $at, 2);
		return $value;
	};

	$take('-t');
	$host = $take('-H') ?: $take('--db-host') ?: 'host.docker.internal';
	$port = $take('--db-port') ?: '3306';
	$database = $take('-n') ?: $take('--db-name');

	array_push(
		$translated,
		'-t', 'generic',
		'--db-driver', 'com.mysql.cj.jdbc.Driver',
		'--db-url', "jdbc:mysql://{$host}:{$port}/{$database}" . ($mysql_params !== '' ? "?{$mysql_params}" : '')
	);
}

if (getenv('JASPERSTARTER_DEBUG')) fwrite(STDERR, 'docker exec ' . $container . ' jasperstarter ' . implode(' ', $translated) . PHP_EOL);

$process = proc_open(
	array_merge(['docker', 'exec', $container, 'jasperstarter'], $translated),
	[1 => STDOUT, 2 => STDERR],
	$pipes
);

if (!is_resource($process)) {
	fwrite(STDERR, "No se pudo ejecutar docker. Esta corriendo Docker Desktop y el contenedor {$container}?" . PHP_EOL);
	exit(1);
}

exit(proc_close($process));
