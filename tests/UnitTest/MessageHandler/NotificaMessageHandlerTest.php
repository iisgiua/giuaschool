<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\MessageHandler;

use App\Tests\DatabaseTestCase;
use App\Message\NotificaMessage;
use App\MessageHandler\NotificaMessageHandler;
use App\Util\TelegramManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;


/**
 * Unit test per la gestione dell'invio delle notifiche
 *
 * @author Antonello Dessì
 */
class NotificaMessageHandlerTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array $tpl Memorizza dati dei template Twig.
   */
  private array $tpl = [];

  /**
   * @var array $msg Memorizza messaggi email.
   */
  private array $email = [];

  /**
   * @var array $msg Memorizza messaggi Telegram.
   */
  private array $telegram = [];

  /**
   * @var array $logs Memorizza i messaggi di log.
   */
  private array $logs = [];

  /**
   * @var $mockedTranslator Gestore delle traduzioni (moked)
   */
  private $mockedTranslator;

  /**
   * @var $mockedEnvironment Gestione template (moked)
   */
  private $mockedEnvironment;

  /**
   * @var $mockedMailer Gestore della spedizione delle email (moked)
   */
  private $mockedMailer;

  /**
   * @var $mockedTelegram Gestore delle comunicazioni tramite Telegram (moked)
   */
  private $mockedTelegram;

  /**
   * @var $mockedLogger Gestore dei log su file (moked)
   */
  private $mockedLogger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // dati da caricare
    $this->fixtures = 'NotificaFixtures';
  }

  /**
 	 * Crea le istanze fittizie per altri servizi
 	 *
 	 */
	protected function mockServices(): void {
    // translator: restituisce tag richiesta
    $this->mockedTranslator = $this->createMock(TranslatorInterface::class);
    $this->mockedTranslator->method('trans')->willReturnCallback(
      function($tag) { return $tag; });
    // environment: inserisce dati in coda template
    $this->mockedEnvironment = $this->createMock(Environment::class);
    $this->mockedEnvironment->method('render')->willReturnCallback(
      function($tpl, $dati) { $this->tpl[] = [$tpl, $dati]; return $tpl; });
    // mailer: inserisce in coda messaggi email
    $this->mockedMailer = $this->createMock(MailerInterface::class);
    $this->mockedMailer->method('send')->willReturnCallback(
      function($msg) { $this->email[] = $msg; });
    // telegramManager: inserisce in coda messaggi Telegram
    $this->mockedTelegram = $this->createMock(TelegramManager::class);
    $this->mockedTelegram->method('sendMessage')->willReturnCallback(
      function($chat, $msg) { $this->telegram[] = [$chat, $msg]; return $chat === '0000' ? ['error' => 'Errore'] : ['result' => 'ok']; });
    // logger: inserisce in coda log
    $this->mockedLogger = $this->createMock(LoggerInterface::class);
    $this->mockedLogger->method('debug')->willReturnCallback(
      function($text, $a) { $this->logs['debug'][] = [$text, $a]; });
    $this->mockedLogger->method('notice')->willReturnCallback(
      function($text, $a) { $this->logs['notice'][] = [$text, $a]; });
    $this->mockedLogger->method('warning')->willReturnCallback(
      function($text, $a) { $this->logs['warning'][] = [$text, $a]; });
    $this->mockedLogger->method('error')->willReturnCallback(
      function($text, $a) { $this->logs['error'][] = [$text, $a]; });
	}

  /**
   * Test invio notifica a utente non esisteste.
   *
   */
  public function testNotificaUtenteNonEsiste(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $utenteId = -1;
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test invio notifica a utente non abilitato.
   *
   */
  public function testNotificaUtenteNonValido(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['circolare', 'avviso']];
    $docente->setAbilitato(false)->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test invio notifica a utente con tipo notifica non abilitata.
   *
   */
  public function testNotificaNonAbilitata(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['avviso']];
    $docente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test invio notifica a utente con canale non valido.
   *
   */
  public function testNotificaCanaleInvalido(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'whatsapp', 'abilitato' => ['circolare', 'avviso']];
    $docente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$conf['tipo']], $this->logs['warning'][0][1]);
  }

  /**
   * Test invio notifica a utente con email nulla.
   *
   */
  public function testNotificaEmailNulla(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['circolare', 'avviso']];
    $docente->setNotifica($conf)->setEmail('');
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test invio notifica a utente con email fittizia.
   *
   */
  public function testNotificaEmailFittizia(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['circolare', 'avviso']];
    $docente->setNotifica($conf)->setEmail('prova@prova.local');
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test invio notifica circolare a utente con canale email.
   *
   */
  public function testNotificaCircolareEmail(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['circolare', 'avviso']];
    $docente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame('email/notifica_circolari.html.twig', $this->tpl[0][0]);
    $this->assertSame(3, count($this->tpl[0][1]));
    $this->assertArrayHasKey('circolari', $this->tpl[0][1]);
    $this->assertArrayHasKey('intestazione_istituto_breve', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(1, $this->email);
    $this->assertSame('message.notifica_circolare_oggetto', $this->email[0]->getSubject());
    $this->assertSame('email/notifica_circolari.html.twig', $this->email[0]->getHtmlBody());
    $this->assertSame($docente->getEmail(), $this->email[0]->getTo()[0]->getAddress());
    $this->assertCount(0, $this->telegram);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$msg, $docente->getEmail()], $this->logs['debug'][0][1]);
  }

  /**
   * Test invio notifica avviso a utente con canale email.
   *
   */
  public function testNotificaAvvisoEmail(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['circolare', 'avviso']];
    $docente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'avviso';
    $avviso = $this->getReference('avviso_A');
    $tag = '<!AVVISO!><!'.$avviso->getId().'!>';
    $dati = ['id' => $avviso->getId(), 'data' => $avviso->getData()->format('d/m/Y'),
      'oggetto' => $avviso->getOggetto(), 'allegati' => count($avviso->getAllegati()),
      'alunno' => '', 'classi' => '3ª D'];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame('email/notifica_avvisi.html.twig', $this->tpl[0][0]);
    $this->assertSame(2, count($this->tpl[0][1]));
    $this->assertArrayHasKey('dati', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(1, $this->email);
    $this->assertSame($dati['oggetto'], substr($this->email[0]->getSubject(), 2 + strpos($this->email[0]->getSubject(), '-')));
    $this->assertSame('email/notifica_avvisi.html.twig', $this->email[0]->getHtmlBody());
    $this->assertSame($docente->getEmail(), $this->email[0]->getTo()[0]->getAddress());
    $this->assertCount(0, $this->telegram);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$msg, $docente->getEmail()], $this->logs['debug'][0][1]);
  }

  /**
   * Test invio notifica verifica a utente con canale email.
   *
   */
  public function testNotificaVerificaEmail(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $utente = $this->getReference('alunno_prima_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['circolare', 'verifica']];
    $utente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $utente->getId();
    $tipo = 'verifica';
    $avviso = $this->getReference('avviso_V');
    $tag = '<!AVVISO!><!'.$avviso->getId().'!>';
    $dati = ['id' => $avviso->getId(), 'data' => $avviso->getData()->format('d/m/Y'),
      'oggetto' => $avviso->getOggetto(), 'allegati' => count($avviso->getAllegati()),
      'alunno' => '', 'classi' => ''];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame('email/notifica_avvisi.html.twig', $this->tpl[0][0]);
    $this->assertSame(2, count($this->tpl[0][1]));
    $this->assertArrayHasKey('dati', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(1, $this->email);
    $this->assertSame($dati['oggetto'], substr($this->email[0]->getSubject(), 2 + strpos($this->email[0]->getSubject(), '-')));
    $this->assertSame('email/notifica_avvisi.html.twig', $this->email[0]->getHtmlBody());
    $this->assertSame($utente->getEmail(), $this->email[0]->getTo()[0]->getAddress());
    $this->assertCount(0, $this->telegram);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$msg, $utente->getEmail()], $this->logs['debug'][0][1]);
  }

  /**
   * Test invio notifica compito a utente con canale email.
   *
   */
  public function testNotificaCompitoEmail(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $utente = $this->getReference('alunno_prima_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['circolare', 'compito']];
    $utente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $utente->getId();
    $tipo = 'compito';
    $avviso = $this->getReference('avviso_P');
    $tag = '<!AVVISO!><!'.$avviso->getId().'!>';
    $dati = ['id' => $avviso->getId(), 'data' => $avviso->getData()->format('d/m/Y'),
      'oggetto' => $avviso->getOggetto(), 'allegati' => count($avviso->getAllegati()),
      'alunno' => '', 'classi' => ''];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame('email/notifica_avvisi.html.twig', $this->tpl[0][0]);
    $this->assertSame(2, count($this->tpl[0][1]));
    $this->assertArrayHasKey('dati', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(1, $this->email);
    $this->assertSame($dati['oggetto'], substr($this->email[0]->getSubject(), 2 + strpos($this->email[0]->getSubject(), '-')));
    $this->assertSame('email/notifica_avvisi.html.twig', $this->email[0]->getHtmlBody());
    $this->assertSame($utente->getEmail(), $this->email[0]->getTo()[0]->getAddress());
    $this->assertCount(0, $this->telegram);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$msg, $utente->getEmail()], $this->logs['debug'][0][1]);
  }

  /**
   * Test invio notifica evento non prevista a utente con canale email.
   *
   */
  public function testNotificaNonPrevistaEmail(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $utente = $this->getReference('alunno_prima_1');
    $conf = ['tipo' => 'email', 'abilitato' => ['circolare', 'altro']];
    $utente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $utente->getId();
    $tipo = 'altro';
    $tag = '<!ALTRO!><!0!>';
    $dati = ['altro' => 'Testo'];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$tipo], $this->logs['warning'][0][1]);
  }

  /**
   * Test invio notifica Telegram a utente con chat nulla.
   *
   */
  public function testNotificaTelegramlChatNulla(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'telegram', 'abilitato' => ['circolare', 'avviso']];
    $docente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test invio notifica Telegram a utente con chat vuota.
   *
   */
  public function testNotificaTelegramlChatVuota(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'telegram', 'abilitato' => ['circolare', 'avviso'], 'telegram_chat' => ''];
    $docente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test invio notifica circolare a utente con canale Telegram.
   *
   */
  public function testNotificaCircolareTelegram(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'telegram', 'abilitato' => ['circolare', 'avviso'], 'telegram_chat' => '111111'];
    $docente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'circolare';
    $circolare = $this->getReference('circolare_perClasse');
    $tag = '<!CIRCOLARE!><!'.$circolare->getId().'!>';
    $dati = ['id' => $circolare->getId(), 'numero' => $circolare->getNumero(),
      'data' => $circolare->getData()->format('d/m/Y'), 'oggetto' => $circolare->getOggetto()];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame('chat/notifica_circolari.html.twig', $this->tpl[0][0]);
    $this->assertSame(2, count($this->tpl[0][1]));
    $this->assertArrayHasKey('circolari', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(0, $this->email);
    $this->assertCount(1, $this->telegram);
    $this->assertSame($conf['telegram_chat'], $this->telegram[0][0]);
    $this->assertSame('chat/notifica_circolari.html.twig', $this->telegram[0][1]);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$msg, $conf['telegram_chat']], $this->logs['debug'][0][1]);
  }

  /**
   * Test invio notifica avviso a utente con canale Telegram.
   *
   */
  public function testNotificaAvvisoTelegram(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $docente = $this->getReference('docente_curricolare_1');
    $conf = ['tipo' => 'telegram', 'abilitato' => ['circolare', 'avviso'], 'telegram_chat' => '111111'];
    $docente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $docente->getId();
    $tipo = 'avviso';
    $avviso = $this->getReference('avviso_V');
    $tag = '<!AVVISO!><!'.$avviso->getId().'!>';
    $dati = ['id' => $avviso->getId(), 'data' => $avviso->getData()->format('d/m/Y'),
      'oggetto' => $avviso->getOggetto(), 'allegati' => count($avviso->getAllegati()),
      'alunno' => '', 'classi' => ''];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame('chat/notifica_avvisi.html.twig', $this->tpl[0][0]);
    $this->assertSame(2, count($this->tpl[0][1]));
    $this->assertArrayHasKey('dati', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(0, $this->email);
    $this->assertCount(1, $this->telegram);
    $this->assertSame($conf['telegram_chat'], $this->telegram[0][0]);
    $this->assertSame('chat/notifica_avvisi.html.twig', $this->telegram[0][1]);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$msg, $conf['telegram_chat']], $this->logs['debug'][0][1]);
  }

  /**
   * Test invio notifica verifica a utente con canale Telegram.
   *
   */
  public function testNotificaVerificaTelegram(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $utente = $this->getReference('alunno_prima_1');
    $conf = ['tipo' => 'telegram', 'abilitato' => ['circolare', 'verifica'], 'telegram_chat' => '111111'];
    $utente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $utente->getId();
    $tipo = 'verifica';
    $avviso = $this->getReference('avviso_V');
    $tag = '<!AVVISO!><!'.$avviso->getId().'!>';
    $dati = ['id' => $avviso->getId(), 'data' => $avviso->getData()->format('d/m/Y'),
      'oggetto' => $avviso->getOggetto(), 'allegati' => count($avviso->getAllegati()),
      'alunno' => '', 'classi' => ''];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame('chat/notifica_avvisi.html.twig', $this->tpl[0][0]);
    $this->assertSame(2, count($this->tpl[0][1]));
    $this->assertArrayHasKey('dati', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(0, $this->email);
    $this->assertCount(1, $this->telegram);
    $this->assertSame($conf['telegram_chat'], $this->telegram[0][0]);
    $this->assertSame('chat/notifica_avvisi.html.twig', $this->telegram[0][1]);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$msg, $conf['telegram_chat']], $this->logs['debug'][0][1]);
  }

  /**
   * Test invio notifica compito a utente con canale Telegram.
   *
   */
  public function testNotificaCompitoTelegram(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $utente = $this->getReference('alunno_prima_1');
    $conf = ['tipo' => 'telegram', 'abilitato' => ['circolare', 'compito'], 'telegram_chat' => '111111'];
    $utente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $utente->getId();
    $tipo = 'compito';
    $avviso = $this->getReference('avviso_P');
    $tag = '<!AVVISO!><!'.$avviso->getId().'!>';
    $dati = ['id' => $avviso->getId(), 'data' => $avviso->getData()->format('d/m/Y'),
      'oggetto' => $avviso->getOggetto(), 'allegati' => count($avviso->getAllegati()),
      'alunno' => '', 'classi' => ''];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame('chat/notifica_avvisi.html.twig', $this->tpl[0][0]);
    $this->assertSame(2, count($this->tpl[0][1]));
    $this->assertArrayHasKey('dati', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(0, $this->email);
    $this->assertCount(1, $this->telegram);
    $this->assertSame($conf['telegram_chat'], $this->telegram[0][0]);
    $this->assertSame('chat/notifica_avvisi.html.twig', $this->telegram[0][1]);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$msg, $conf['telegram_chat']], $this->logs['debug'][0][1]);
  }

  /**
   * Test invio notifica di tipo non previsto a utente con canale Telegram.
   *
   */
  public function testNotificaNonPrevistaTelegram(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $utente = $this->getReference('alunno_prima_1');
    $conf = ['tipo' => 'telegram', 'abilitato' => ['circolare', 'altro'], 'telegram_chat' => '111111'];
    $utente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $utente->getId();
    $tipo = 'altro';
    $tag = '<!ALTRO!><!0!>';
    $dati = ['altro' => 'Testo'];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->tpl);
    $this->assertCount(0, $this->email);
    $this->assertCount(0, $this->telegram);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$tipo], $this->logs['warning'][0][1]);
  }

  /**
   * Test invio notifica con lancio eccezione in procedura di spedizione.
   *
   */
  public function testNotificaEccezione(): void {
    // init
    $this->tpl = [];
    $this->email = [];
    $this->telegram = [];
    $this->logs = [];
    $nmh = new NotificaMessageHandler($this->em, $this->mockedTranslator, $this->mockedEnvironment,
      $this->mockedMailer, $this->mockedTelegram, $this->mockedLogger);
    $utente = $this->getReference('alunno_prima_1');
    $conf = ['tipo' => 'telegram', 'abilitato' => ['circolare', 'avviso'], 'telegram_chat' => '0000'];
    $utente->setNotifica($conf);
    $this->em->flush();
    $utenteId = $utente->getId();
    $tipo = 'avviso';
    $avviso = $this->getReference('avviso_V');
    $tag = '<!AVVISO!><!'.$avviso->getId().'!>';
    $dati = ['id' => $avviso->getId(), 'data' => $avviso->getData()->format('d/m/Y'),
      'oggetto' => $avviso->getOggetto(), 'allegati' => count($avviso->getAllegati()),
      'alunno' => '', 'classi' => ''];
    $msg = new NotificaMessage($utenteId, $tipo, $tag, $dati);
    // esegue
    $nmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->tpl);
    $this->assertSame(2, count($this->tpl[0][1]));
    $this->assertArrayHasKey('dati', $this->tpl[0][1]);
    $this->assertArrayHasKey('url_registro', $this->tpl[0][1]);
    $this->assertCount(0, $this->email);
    $this->assertCount(1, $this->telegram);
    $this->assertSame($conf['telegram_chat'], $this->telegram[0][0]);
    $this->assertSame('chat/notifica_avvisi.html.twig', $this->telegram[0][1]);
    $this->assertCount(1, $this->logs);
    $this->assertSame([$conf['telegram_chat']], $this->logs['error'][0][1]);
  }

  /**
   * Test cancellazione notifica in coda.
   *
   */
  public function testNotificaDelete(): void {
    // init
    $connection = $this->em->getConnection();
    $sql = "TRUNCATE gs_messenger_messages;".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:15:\"<!AVVISO!><!1!>\";}}', '[1]', 'avviso', NOW(), '2023-01-01 00:00:00', NULL);".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:15:\"<!AVVISO!><!1!>\";}}', '[2]', 'notifica', NOW(), '2023-01-01 00:00:00', NULL);".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:17:\"<!AVVISO!><!134!>\";}}', '[3]', 'notifica', NOW(), '2023-01-01 00:00:00', NULL);";
    $connection->prepare($sql)->executeStatement();
    // esegue
    NotificaMessageHandler::delete($this->em, '<!AVVISO!><!1!>');
    // controlla
    $sql = "SELECT * FROM gs_messenger_messages ORDER BY headers";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $rset = $result->fetchAllAssociative();
    $this->assertCount(1, $rset);
    $this->assertSame('[3]', $rset[0]['headers']);
  }

  /**
   * Test aggiornamento notifica in coda.
   *
   */
  public function testNotificaUpdate(): void {
    // init
    $connection = $this->em->getConnection();
    $sql = "TRUNCATE gs_messenger_messages;".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:15:\"<!AVVISO!><!1!>\";}}', '[1]', 'avviso', NOW(), '2023-01-01 00:00:00', NULL);".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:15:\"<!AVVISO!><!1!>\";}}', '[2]', 'notifica', NOW(), '2023-01-01 00:00:00', NULL);".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:17:\"<!AVVISO!><!134!>\";}}', '[3]', 'notifica', NOW(), '2023-01-01 00:00:00', NULL);";
    $connection->prepare($sql)->executeStatement();
    // esegue
    $adesso = new \DateTime();
    $aggiornato = NotificaMessageHandler::update($this->em, '<!AVVISO!><!1!>', 'avviso', 3600);
    // controlla
    $sql = "SELECT * FROM gs_messenger_messages ORDER BY headers";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $rset = $result->fetchAllAssociative();
    $this->assertTrue($aggiornato);
    $this->assertCount(3, $rset);
    $this->assertSame(['[1]', $adesso->modify('+3600 sec')->format('Y-m-d H:i')], [$rset[0]['headers'], substr($rset[0]['available_at'], 0, 16)]);
    $this->assertSame(['[2]', '2023-01-01 00:00:00'], [$rset[1]['headers'], $rset[1]['available_at']]);
    $this->assertSame(['[3]', '2023-01-01 00:00:00'], [$rset[2]['headers'], $rset[2]['available_at']]);
  }

  /**
   * Test aggiornamento notifica in coda.
   *
   */
  public function testNotificaUpdateDelete(): void {
    // init
    $connection = $this->em->getConnection();
    $sql = "TRUNCATE gs_messenger_messages;".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:15:\"<!AVVISO!><!134!>\";}}', '[1]', 'avviso', NOW(), '2023-01-01 00:00:00', NULL);".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:15:\"<!AVVISO!><!1!>\";}}', '[2]', 'notifica', NOW(), '2023-01-01 00:00:00', NULL);".
      "INSERT INTO gs_messenger_messages (body, headers, queue_name, created_at, available_at, delivered_at) VALUES ('O:36:\"Symfony\\Component\\Messenger\\Envelope\":2:{s:44:\"\0Symfony\\Component\\Messenger\\Envelope\0stamps\";a:2:{s:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\";a:1:{i:0;O:44:\"Symfony\\Component\\Messenger\\Stamp\\DelayStamp\":1:{s:51:\"\0Symfony\\Component\\Messenger\\Stamp\\DelayStamp\0delay\";i:1800000;}}s:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\";a:1:{i:0;O:46:\"Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\":1:{s:55:\"\0Symfony\\Component\\Messenger\\Stamp\\BusNameStamp\0busName\";s:21:\"messenger.bus.default\";}}}s:45:\"\0Symfony\\Component\\Messenger\\Envelope\0message\";O:25:\"App\\Message\\AvvisoMessage\":2:{s:29:\"\0App\\Message\\AvvisoMessage\0id\";i:35536;s:30:\"\0App\\Message\\AvvisoMessage\0tag\";s:17:\"<!AVVISO!><!134!>\";}}', '[3]', 'notifica', NOW(), '2023-01-01 00:00:00', NULL);";
    $connection->prepare($sql)->executeStatement();
    // esegue
    $adesso = new \DateTime();
    $aggiornato = NotificaMessageHandler::update($this->em, '<!AVVISO!><!1!>', 'avviso', 3600);
    // controlla
    $sql = "SELECT * FROM gs_messenger_messages ORDER BY headers";
    $stmt = $connection->prepare($sql);
    $result = $stmt->executeQuery();
    $rset = $result->fetchAllAssociative();
    $this->assertFalse($aggiornato);
    $this->assertCount(2, $rset);
    $this->assertSame(['[1]', '2023-01-01 00:00:00'], [$rset[0]['headers'], $rset[0]['available_at']]);
    $this->assertSame(['[3]', '2023-01-01 00:00:00'], [$rset[1]['headers'], $rset[1]['available_at']]);
  }

}
