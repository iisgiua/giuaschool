<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\MessageHandler;

use App\Tests\DatabaseTestCase;
use App\Message\CircolareMessage;
use App\MessageHandler\CircolareMessageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\MessageBusInterface;


/**
 * Unit test per il gestore delle notifiche per le circolari
 *
 * @author Antonello Dessì
 */
class CircolareMessageHandlerTest extends DatabaseTestCase {


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

  /**
   * @var $mockedAcknowledger Gestore della risposta per messaggi asincroni (moked)
   */
  private $mockedAcknowledger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // dati da caricare
    $this->fixtures = 'CircolareFixtures';
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
    // acknowledger: gestione risposta di invio asincrono
    $this->mockedAcknowledger = $this->createMock(Acknowledger::class);
	}

  /**
   * Invio messaggio per circolare non più presente nel database.
   *
   */
  public function testCircolareNonPresente(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $cmh = new CircolareMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $msg = new CircolareMessage(-1);
    // esegue
    $cmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->logs);
    $this->assertEquals([[], 0], $this->logs['notice'][0][1]);
    $this->assertCount(0, $this->bus);
  }

  /**
   * Invio messaggio per circolare non pubblicata.
   *
   */
  public function testCircolareNonPubblicata(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $cmh = new CircolareMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $circolare = $this->getReference('circolare_nonPubblicata');
    $msg = new CircolareMessage($circolare->getId());
    // esegue
    $cmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->logs);
    $this->assertEquals([[], 0], $this->logs['notice'][0][1]);
    $this->assertCount(0, $this->bus);
  }

  /**
   * Invio messaggio per circolare già letta dai destinatari.
   *
   */
  public function testCircolareLetta(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $cmh = new CircolareMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $circolare = $this->getReference('circolare_perDocenti');
    $msg = new CircolareMessage($circolare->getId());
    $risultato = $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
      ->update()
      ->set('cu.letta', ':ora')
      ->set('cu.confermata', ':ora')
      ->where('cu.circolare=:circolare')
      ->setParameters(['ora'=> new \DateTime(), 'circolare' => $circolare->getId()])
      ->getQuery()
      ->getResult();
    // esegue
    $cmh->__invoke($msg);
    // controlla
    $this->assertCount(1, $this->logs);
    $this->assertEquals([[$circolare->getId()], 0], $this->logs['notice'][0][1]);
    $this->assertCount(0, $this->bus);
  }

  /**
   * Invio messaggio per circolare normale.
   *
   */
  public function testCircolareNormale(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $cmh = new CircolareMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    $circolare = $this->getReference('circolare_perClasse');
    $msg = new CircolareMessage($circolare->getId());
    $risultato = $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
      ->update()
      ->set('cu.letta', ':nulla')
      ->set('cu.confermata', ':nulla')
      ->where('cu.circolare=:circolare')
      ->setParameters(['nulla'=> null, 'circolare' => $circolare->getId()])
      ->getQuery()
      ->getResult();
    // esegue
    $cmh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertEquals('circolare', $notifica->getTipo());
      $this->assertEquals('<!CIRCOLARE!><!'.$circolare->getId().'!>', $notifica->getTag());
      $this->assertEquals(1, count($notifica->getDati()));
      $this->assertEquals($circolare->getId(), $notifica->getDati()[0]['id']);
      $this->assertEquals($circolare->getNumero(), $notifica->getDati()[0]['numero']);
      $this->assertEquals($circolare->getData()->format('d/m/Y'), $notifica->getDati()[0]['data']);
      $this->assertEquals($circolare->getOggetto(), $notifica->getDati()[0]['oggetto']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertEquals([[$circolare->getId()], count($this->bus)], $this->logs['notice'][0][1]);
  }

  /**
   * Invio messaggi per più circolari raggruppate.
   *
   */
  public function testCircolareConRaggruppamento(): void {
    // init
    $listaCircolari = [];
    $this->logs = [];
    $this->bus = [];
    $cmh = new CircolareMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    // prima circolare
    $circolare1 = $this->getReference('circolare_perClasse');
    $listaCircolari[$circolare1->getId()] = $circolare1;
    $msg = new CircolareMessage($circolare1->getId());
    $risultato = $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
      ->update()
      ->set('cu.letta', ':nulla')
      ->set('cu.confermata', ':nulla')
      ->where('cu.circolare=:circolare')
      ->setParameters(['nulla'=> null, 'circolare' => $circolare1->getId()])
      ->getQuery()
      ->getResult();
    // esegue: inserisce in coda
    $cmh->__invoke($msg, $this->mockedAcknowledger);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->bus);
    // seconda circolare
    $circolare2 = $this->getReference('circolare_perDocenti');
    $listaCircolari[$circolare2->getId()] = $circolare2;
    $msg = new CircolareMessage($circolare2->getId());
    $risultato = $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
      ->update()
      ->set('cu.letta', ':nulla')
      ->set('cu.confermata', ':nulla')
      ->where('cu.circolare=:circolare')
      ->setParameters(['nulla'=> null, 'circolare' => $circolare2->getId()])
      ->getQuery()
      ->getResult();
    // esegue: inserisce in coda
    $cmh->__invoke($msg, $this->mockedAcknowledger);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->bus);
    // terza circolare
    $circolare3 = $this->getReference('circolare_conAllegato');
    $listaCircolari[$circolare3->getId()] = $circolare3;
    $msg = new CircolareMessage($circolare3->getId());
    $risultato = $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
      ->update()
      ->set('cu.letta', ':nulla')
      ->set('cu.confermata', ':nulla')
      ->where('cu.circolare=:circolare')
      ->setParameters(['nulla'=> null, 'circolare' => $circolare3->getId()])
      ->getQuery()
      ->getResult();
    // esegue: invia tutta la coda
    $cmh->__invoke($msg);
    // controlla
    $risultato = $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
      ->select('(cu.utente) AS utente,(cu.circolare) AS circolare')
      ->where('cu.circolare IN (:circolari)')
      ->setParameters(['circolari' => [$circolare1->getId(), $circolare2->getId(), $circolare3->getId()]])
      ->getQuery()
      ->getArrayResult();
    $destinatari = [];
    foreach ($risultato as $dato) {
      $destinatari[$dato['utente']][] = $dato['circolare'];
    }
    $this->assertEquals(count($destinatari), count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertEquals('circolare', $notifica->getTipo());
      $utente = $notifica->getUtenteId();
      $this->assertEquals(count($destinatari[$utente]), count($notifica->getDati()));
      $this->assertEquals('<!CIRCOLARE!><!'.implode(',', $destinatari[$utente]).'!>', $notifica->getTag());
      foreach ($notifica->getDati() as $dati) {
        $this->assertTrue(in_array($dati['id'], $destinatari[$utente]));
        $this->assertEquals($listaCircolari[$dati['id']]->getNumero(), $dati['numero']);
        $this->assertEquals($listaCircolari[$dati['id']]->getData()->format('d/m/Y'), $dati['data']);
        $this->assertEquals($listaCircolari[$dati['id']]->getOggetto(), $dati['oggetto']);
      }
    }
    $this->assertCount(1, $this->logs);
    $this->assertEquals([[$circolare1->getId(), $circolare2->getId(), $circolare3->getId()], count($this->bus)], $this->logs['notice'][0][1]);
  }

  /**
   * Invio messaggi per più circolari ripetute nella coda.
   *
   */
  public function testCircolareInCoda(): void {
    // init
    $this->logs = [];
    $this->bus = [];
    $cmh = new CircolareMessageHandler($this->em, $this->mockedLogger, $this->mockedMessageBus);
    // prima circolare
    $circolare = $this->getReference('circolare_perClasse');
    $msg = new CircolareMessage($circolare->getId());
    $risultato = $this->em->getRepository('App\Entity\CircolareUtente')->createQueryBuilder('cu')
      ->update()
      ->set('cu.letta', ':nulla')
      ->set('cu.confermata', ':nulla')
      ->where('cu.circolare=:circolare')
      ->setParameters(['nulla'=> null, 'circolare' => $circolare->getId()])
      ->getQuery()
      ->getResult();
    // esegue: inserisce in coda
    $cmh->__invoke($msg, $this->mockedAcknowledger);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertCount(0, $this->bus);
    // seconda circolare: uguale alla precedente
    $msg = new CircolareMessage($circolare->getId());
    // esegue: invia tutta la coda
    $cmh->__invoke($msg);
    // controlla
    $this->assertGreaterThan(0, count($this->bus));
    foreach ($this->bus as $notifica) {
      $this->assertEquals('circolare', $notifica->getTipo());
      $this->assertEquals('<!CIRCOLARE!><!'.$circolare->getId().'!>', $notifica->getTag());
      $this->assertEquals(1, count($notifica->getDati()));
      $this->assertEquals($circolare->getId(), $notifica->getDati()[0]['id']);
      $this->assertEquals($circolare->getNumero(), $notifica->getDati()[0]['numero']);
      $this->assertEquals($circolare->getData()->format('d/m/Y'), $notifica->getDati()[0]['data']);
      $this->assertEquals($circolare->getOggetto(), $notifica->getDati()[0]['oggetto']);
    }
    $this->assertCount(1, $this->logs);
    $this->assertEquals([[$circolare->getId()], count($this->bus)], $this->logs['notice'][0][1]);
  }

}
