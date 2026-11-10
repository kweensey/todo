<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SmtpException;
use App\Models\User;
use Oasys\Config\ArrayConfig as Config;
use PHPMailer\PHPMailer\PHPMailer;
use Twig\Environment as Twig;

final class EmailNotifier
{
  public function __construct(
    private PHPMailer $mailerPrototype,
    private Twig $template,
    private Config $config
  ) {}

  public function sendInvitation(User $user, string $token): void
  {
    $appName = $this->config->get('app.name');

    $link = rtrim($this->config->get('app.host'), '/')
      . '/user/'
      . $user->getUuid()
      . '/register?token='
      . rawurlencode($token);

    $this->send(
      $user->email,
      'Pozvánka do aplikace ' . $appName,
      [
        'appName' => $appName,
        'text'    => 'byli jste pozváni do aplikace '
          . $appName
          . ".\n\nPro registraci klikněte na následující odkaz:",
        'button'  => [
          'title' => 'Registrovat',
          'link'  => $link
        ]
      ]
    );
  }

  public function sendRegistrationCode(string $recipient, string $code): void
  {
    $this->send(
      $recipient,
      'Ověření e-mailové adresy',
      [
        'appName' => $this->config->get('app.name'),
        'text'    => 'kód pro ověření Vaší e-mailové adresy:',
        'code'    => $code
      ]
    );
  }

  public function sendEmailChangeCode(string $recipient, string $code): void
  {
    $this->send(
      $recipient,
      'Ověření nové e-mailové adresy',
      [
        'appName' => $this->config->get('app.name'),
        'text'    => 'kód pro ověření Vaší nové e-mailové adresy:',
        'code'    => $code
      ]
    );
  }

  public function sendRecovery(User $user, string $token): void
  {
    $appName = $this->config->get('app.name');

    $link = rtrim($this->config->get('app.host'), '/')
      . '/user/'
      . $user->getUuid()
      . '/recover?token='
      . rawurlencode($token);

    $this->send(
      $user->email,
      'Obnovení hesla',
      [
        'appName' => $appName,
        'text'    => 'žádost o obnovení hesla do aplikace '
          . $appName
          . " byla přijata.\n\nPro obnovení hesla klikněte na následující odkaz:",
        'button'  => [
          'title' => 'Obnovit heslo',
          'link'  => $link
        ]
      ]
    );
  }

  public function sendWelcome(User $user): void
  {
    $appName = $this->config->get('app.name');

    $this->send(
      $user->email,
      'Registrace dokončena',
      [
        'appName' => $appName,
        'link'    => rtrim($this->config->get('app.host'), '/'),
        'text'    => 'Vaše registrace do aplikace '
          . $appName
          . " byla úspěšně dokončena.\n\nPro přihlášení použijte následující odkaz:"
      ]
    );
  }

  public function sendLoginLockout(User $user): void
  {
    $appName = $this->config->get('app.name');

    $this->send(
      $user->email,
      'Upozornění na neúspěšné přihlášení',
      [
        'appName' => $appName,
        'text'    => 'U Vašeho účtu v aplikaci '
          . $appName
          . ' bylo zaznamenáno příliš mnoho neúspěšných pokusů '
          . 'o přihlášení. Další pokus bude možný po uplynutí '
          . 'bezpečnostní prodlevy.'
      ]
    );
  }

  private function send(string $recipient, string $subject, array $body): void
  {
    $mail = clone $this->mailerPrototype;

    $mail->addEmbeddedImage(
      APP_DIR . '/public/assets/img/logo.png',
      'logo'
    );

    $mail->Subject = $subject;

    $mail->msgHTML(
      $this->template->render('misc/email.html.twig', $body),
      APP_DIR
    );

    $mail->addAddress($recipient);

    $mail->send()
      || throw new SmtpException('Failed to send email');
  }
}
