<?php declare(strict_types=1);

$root = dirname(__DIR__);

umask(0077);

foreach (['app', 'db', 'mail'] as $name) {
  $file = $root . '/config/' . $name . '.php';

  if (! is_file($file) && ! copy($file . '.dist', $file)) {
    throw new RuntimeException('Cannot create configuration: ' . $name);
  }
}

$directory = $root . '/cert';

if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
  throw new RuntimeException('Cannot create certificate directory');
}

$private = $directory . '/private.key';
$public  = $directory . '/public.pem';

if (is_file($private) !== is_file($public)) {
  throw new RuntimeException('Incomplete JWT signing key pair');
}

if (! is_file($private)) {
  $key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 4096
  ]);

  if ($key === false || ! openssl_pkey_export($key, $privatePem)) {
    throw new RuntimeException('Cannot generate JWT private key');
  }

  $publicPem = openssl_pkey_get_details($key)['key'] ?? null;

  if (! is_string($publicPem)) {
    throw new RuntimeException('Cannot generate JWT public key');
  }

  if (file_put_contents($private, $privatePem) === false
    || file_put_contents($public, $publicPem) === false
  ) {
    throw new RuntimeException('Cannot save JWT signing key pair');
  }
}

$privateKey = @openssl_pkey_get_private('file://' . $private);
$publicKey  = @openssl_pkey_get_public('file://' . $public);

if ($privateKey === false
  || $publicKey === false
  || ! openssl_sign('key-check', $signature, $privateKey, OPENSSL_ALGO_SHA256)
  || openssl_verify('key-check', $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1
) {
  throw new RuntimeException('JWT signing key pair invalid or mismatched');
}

fwrite(STDOUT, "JWT signing key pair created.\n");
fwrite(STDOUT, "Default configuration created.\n");
fwrite(STDOUT, "Change hmacKey in /config/app.php for a random string, e.g.:\n");
fwrite(STDOUT, bin2hex(random_bytes(32)) . "\n");
