<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\MessageHandler;

use App\Entity\Alunno;
use App\Entity\Classe;
use App\Entity\ComunicazioneUtente;
use App\Entity\Genitore;
use App\Entity\Utente;
use App\Message\AvvisoMessage;
use App\MessageHandler\AvvisoMessageHandler;
use App\Tests\DatabaseTestCase;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * Unit test per il gestore delle notifiche per gli avvisi
 *
 * @author Antonello Dessì
 */
class AvvisoMessageHandlerTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array $logs Memorizza i messaggi di log.
   */
  private array $logs = [];

  /**
   * @var array $bus Memorizza le notifiche inserite nella coda.
   */
  private array $bus = [];

  /**
   * @var $mockedLogger Gestore dei log su file (moked)
   */
  private $mockedLogger;

  /**
   * @var $mockedMessageBus Gestore della coda dei messaggi (moked)
   */
  private $mockedMessageBus;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AvvisoFixtures', 'ComunicazioneClasseFixtures', 'ComunicazioneUtenteFixtures'];
    // esegue il setup predefinito
    parent::setUp();
  }

  /**
 	 * Crea le istanze fittizie per altri servizi
 	 *
 	 */
	protected function mockServices(): void {
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
    // messageBus: gestione invio messaggi
    $this->mockedMessageBus = $this->createMock(MessageBusInterface::class);
    $this->mockedMessageBus->method('dispatch')->willReturnCallback(
      function($m) { $this->bus[] = $m; return new Envelope($m); });
	}

  /**
   * Invio messaggio con avviso non presente nel database.
   *
   */
  public function testAvvisoInesistente(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $msg = new AvvisoMessage(-1);
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->bus);
  }

  /**
   * Invio messaggio di avviso già letto dai destinatari.
   *
   */
  public function testAvvisoLetto(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_C');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':ora')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('ora', new DateTime())
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), 0], $this->logs['notice'][0][1]);
    $this->assertCount(0, $this->bus);
  }

  /**
   * Invio messaggio di avviso per uscite anticipate.
   *
   */
  public function testAvvisoUscita(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_U');
    $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('avviso', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertSame(0, $notifica->getDati()['allegati']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /**
   * Invio messaggio di avviso per entrate posticipate.
   *
   */
  public function testAvvisoEntrata(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_E');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('avviso', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertSame(0, $notifica->getDati()['allegati']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /**
   * Invio messaggio di avviso per attività pianificate.
   *
   */
  public function testAvvisoAttivita(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_A');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('avviso', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertSame(0, $notifica->getDati()['allegati']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /**
   * Invio messaggio di avviso per messaggi individuali.
   *
   */
  public function testAvvisoIndividuale(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_I');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('avviso', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertSame(0, $notifica->getDati()['allegati']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /*
   * Invio messaggio di avviso per messaggi dai docenti.
   *
   */
  public function testAvvisoDocente(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_D');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('avviso', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertSame(0, $notifica->getDati()['allegati']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /*
   * Invio messaggio di avviso per messaggi dai coordinatori.
   *
   */
  public function testAvvisoCoordinatore(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_O');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('avviso', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertSame(0, $notifica->getDati()['allegati']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /*
   * Invio messaggio di avviso per le verifiche.
   *
   */
  public function testAvvisoVerifica(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_V');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('verifica', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertSame(0, $notifica->getDati()['allegati']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /*
   * Invio messaggio di avviso per i compiti.
   *
   */
  public function testAvvisoCompito(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_P');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('compito', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertSame(0, $notifica->getDati()['allegati']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /*
   * Invio messaggio di avviso per una comunicazione generica.
   *
   */
  public function testAvvisoGenerico(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_C');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('avviso', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertCount($notifica->getDati()['allegati'], $avviso->getAllegati());
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /*
   * Invio messaggio di avviso per una comunicazione con allegato.
   *
   */
  public function testAvvisoConAllegato(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_C_allegato');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertSame('avviso', $notifica->getTipo());
      $this->assertSame('<!AVVISO!><!'.$avviso->getId().'!>', $notifica->getTag());
      $this->assertSame($avviso->getId(), $notifica->getDati()['id']);
      $this->assertSame($avviso->getData()->format('d/m/Y'), $notifica->getDati()['data']);
      $this->assertSame($avviso->getTitolo(), $notifica->getDati()['oggetto']);
      $this->assertSame($avviso->getTesto(), $notifica->getDati()['testo']);
      $this->assertCount($notifica->getDati()['allegati'], $avviso->getAllegati());
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /*
   * Invio messaggio di avviso con testo da sostituire.
   *
   */
  public function testAvvisoConSostituzione(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_C');
    $avviso->setSostituzioni(['{DATA}' => $avviso->getData()->format('d/m/Y'), '{ORA}' => '09:10:00', '{INIZIO}' => '09:10:00', '{FINE}' => '11:30:00']);
    $avviso->setTesto('Questa è una data: {DATA}. Questo è un orario: {ORA}. Questo è un orario di inizio: {INIZIO}. Questo è un orario di fine: {FINE}.');
    $this->em->flush();
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    $testo = 'Questa è una data: '.$avviso->getData()->format('d/m/Y').'. Questo è un orario: '.
      $avviso->getSostituzioni()['{ORA}'].'. Questo è un orario di inizio: '.$avviso->getSostituzioni()['{INIZIO}'].
      '. Questo è un orario di fine: '.$avviso->getSostituzioni()['{FINE}'].'.';
    foreach ($this->bus as $notifica) {
      $this->assertSame($testo, $notifica->getDati()['testo']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

  /**
   * Invio messaggio di avviso per attività con informazioni aggiuntive per gli utenti destinatari.
   *
   */
  public function testAvvisoInfoUtenti(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $amh = new AvvisoMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $avviso = $this->getReference('avviso_A');
    $risultato = $this->em->getRepository(ComunicazioneUtente::class)->createQueryBuilder('cu')
      ->update()
      ->set('cu.letto', ':nulla')
      ->where('cu.comunicazione=:avviso')
      ->setParameter('nulla', null)
      ->setParameter('avviso', $avviso->getId())
      ->getQuery()
      ->getResult();
    $msg = new AvvisoMessage($avviso->getId());
    // esegue
    $amh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $utente = $this->em->getRepository(Utente::class)->find($notifica->getUtenteId());
      if ($utente instanceOf Genitore) {
        $alunno = $utente->getAlunno()->getNome().' '.$utente->getAlunno()->getCognome();
        $this->assertSame($alunno, $notifica->getDati()['alunno']);
        $this->assertSame('', $notifica->getDati()['classi']);
      } elseif ($utente instanceOf Alunno) {
        $this->assertSame('', $notifica->getDati()['alunno']);
        $this->assertSame('', $notifica->getDati()['classi']);
      } else {
        $classe = $this->em->getRepository(Classe::class)->find($avviso->getFiltroDocenti()[0]);
        $this->assertSame('', $notifica->getDati()['alunno']);
        $this->assertSame($classe->getAnno().'ª '.$classe->getSezione(), $notifica->getDati()['classi']);
      }
    }
    $this->assertCount(1, $this->logs);
    $this->assertSame([$avviso->getId(), count($this->bus)], $this->logs['notice'][0][1]);
  }

}
