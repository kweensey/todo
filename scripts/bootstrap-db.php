<?php declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  exit(1);
}

$root = dirname(__DIR__);
$configFile = $root . '/config/db.php';
$schemaFile = $root . '/schema.sql';

try {
  if (! is_file($configFile)) {
    throw new RuntimeException('Missing config/db.php. Run composer setup and configure the database first.');
  }

  $config = require $configFile;

  if (! is_array($config) || ($config['driver'] ?? null) !== 'mysql') {
    throw new RuntimeException('Expected a MySQL connection in config/db.php.');
  }

  foreach (['host', 'database', 'username', 'password'] as $field) {
    if (! isset($config[$field]) || ! is_string($config[$field])) {
      throw new RuntimeException("Invalid database configuration: {$field}.");
    }
  }

  if ($config['database'] === '') {
    throw new RuntimeException('Database name cannot be empty.');
  }

  $dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['host'],
    $config['database'],
    $config['charset'] ?? 'utf8mb4'
  );

  $db = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);

  if ($db->query('SHOW TABLES')->fetch() !== false) {
    throw new RuntimeException('Refusing to initialize a database that already contains tables or views.');
  }

  $sql = file_get_contents($schemaFile);

  if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('schema.sql is missing or empty.');
  }

  $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));

  foreach ($statements as $statement) {
    $db->exec($statement);
  }

  fwrite(STDOUT, sprintf(
    "Database '%s' initialized (%d statements).\n",
    $config['database'],
    count($statements)
  ));
} catch (Throwable $error) {
  fwrite(STDERR, "Database initialization failed: {$error->getMessage()}\n");
  fwrite(STDERR, "MySQL DDL is not transactionally rolled back. If some tables were created, inspect the database before retrying.\n");

  exit(1);
}
