<?php declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Oasys\Config\ArrayConfig;
use Oasys\Validation\Validator;
use Psr\Log\LoggerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

return (new ContainerBuilder)
  ->addDefinitions([
    ArrayConfig::class => function ()
    {
      return new ArrayConfig(
        APP_DIR . '/config/app.php',
        APP_DIR . '/config/mail.php'
      );
    },

    Validator::class => function ()
    {
      return new Validator(
        patterns: [
          'decimal'      => '/\A[0-9]+(?:[.,][0-9]+)?\z/',
          'numeric'      => '/\A[0-9]+\z/',
          'alpha'        => '/\A[A-Za-z]+\z/',
          'alphanumeric' => '/\A[A-Za-z0-9]+\z/',
          'hasnumber'    => '/[0-9]/',
          'hasletter'    => '/[A-Za-z]/',
          'hexadecimal'  => '/\A[a-fA-F0-9]+\z/',
          'bit'          => '/\A[01]+\z/',
          'date'         => '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',
          'uuid'         => '/\A[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}\z/',
          'phone'        => '/\A\+42[01][0-9]{9}\z/',
          'email'        => '/\A[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\z/',
          'zip'          => '/\A[0-9]{5}\z/'
        ],
        messages: [
          'require'      => 'Povinné',
          'allow'        => 'Nepovoleno',
          'notEmpty'     => 'Povinné',
          'regex'        => 'Nesprávný formát',
          'format'       => 'Nesprávný formát',
          'type'         => 'Nesprávný typ',
          'inEnum'       => 'Nesprávná hodnota',
          'itemsIn'      => 'Nesprávná hodnota',
          'length'       => 'Musí mít {length} znaků',
          'maxLength'    => 'Musí mít nejvýše {maxLength} znaků',
          'minLength'    => 'Musí mít nejméně {minLength} znaků',
          'equalToField' => 'Musí být rovno poli {compareField}'
        ]
      );
    },

    Environment::class => function ()
    {
      return new Environment(
        new FilesystemLoader(APP_DIR . '/template')
      );
    },

    PHPMailer::class => function (ArrayConfig $config): PHPMailer
    {
      $mail = new PHPMailer();
      
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->SMTPAuth   = true;
      $mail->Host       = $config->get('mail.host');
      $mail->Port       = $config->get('mail.port');
      $mail->Username   = $config->get('mail.username');
      $mail->Password   = $config->get('mail.password');
      $mail->CharSet    = $config->get('mail.charset');

      $mail->isSMTP();
      $mail->isHTML(true);
      
      $mail->setFrom(
        $config->get('mail.from'),
        $config->get('app.name')
      ) || throw new RuntimeException('Invalid mail sender configuration');

      return $mail;
    },

    LoggerInterface::class => function ()
    {
      $logger = new Logger('log');

      $handler = new StreamHandler(
        APP_DIR . '/log/' . date('Y-m-d') . '.log',
        filePermission: 0600
      );

      $logger->pushHandler($handler);

      return $logger;
    },
  ])
  ->build();
