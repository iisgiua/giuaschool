<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Security;

use App\Security\AuthenticatorTrait;
use App\Tests\DatabaseTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;


/**
 * Unit test per le funzioni di uso generale per l'autenticazione
 *
 * @author Antonello Dessì
 */
class AuthenticatorTraitTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var array $logs Memorizza i messaggi di log.
   */
  private array $logs = [];

  /**
   * @var $testedTrait Specifica il codice da testare
   */
  private $testedTrait;

  /**
   * @var $mockedLogger Gestore dei log su file (moked)
   */
  private $mockedLogger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AmministratoreFixtures', 'AtaFixtures', 'ConfigurazioneFixtures',
      'DocenteFixtures', 'GenitoreFixtures', 'PresideFixtures', 'StaffFixtures', 'UtenteFixtures'];
    // esegue il setup standard
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
    // Authenticator trait
    $this->testedTrait = $this->getMockForTrait(AuthenticatorTrait::class);
    $this->testedTrait->em = $this->em;
    $this->testedTrait->logger = $this->mockedLogger;
	}

  /**
   * Test sul controllo della modalità di manutenzione attivata.
   *
   */
  public function testManutenzioneAttivata(): void {
    // init
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    $inizio = (new \DateTime())->modify('-1 min');
    $fine = (new \DateTime())->modify('+1 min');
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_inizio', $inizio->format('Y-m-d H:i'));
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_fine', $fine->format('Y-m-d H:i'));
    // esegue
    try {
      $exception = null;
      $this->testedTrait->controllaManutenzione($utente);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    // controlla
    $this->assertSame('exception.blocked_login', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $utente->getUsername(), 'ruolo' => $utente->getCodiceRuolo()], $this->logs['error'][0][1]);
  }

  /**
   * Test sul controllo della modalità di manutenzione disattivata.
   *
   */
  public function testManutenzioneDisattivata(): void {
    // init
    $this->logs = [];
    $utente = $this->getReference('docente_curricolare_1');
    // periodo nullo
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_inizio', '');
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_fine', '');
    // esegue
    $this->testedTrait->controllaManutenzione($utente);
    // controlla
    $this->assertCount(0, $this->logs);
    // periodo trascorso
    $inizio = (new \DateTime())->modify('-1 hour');
    $fine = (new \DateTime())->modify('-1 min');
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_inizio', $inizio->format('Y-m-d H:i'));
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_fine', $fine->format('Y-m-d H:i'));
    // esegue
    $this->testedTrait->controllaManutenzione($utente);
    // controlla
    $this->assertCount(0, $this->logs);
    // periodo futuro
    $inizio = (new \DateTime())->modify('+1 min');
    $fine = (new \DateTime())->modify('+1 hour');
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_inizio', $inizio->format('Y-m-d H:i'));
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_fine', $fine->format('Y-m-d H:i'));
    // esegue
    $this->testedTrait->controllaManutenzione($utente);
    // controlla
    $this->assertCount(0, $this->logs);
    // periodo di manutenzione con amministratore
    $utente = $this->getReference('amministratore');
    $inizio = (new \DateTime())->modify('-1 hour');
    $fine = (new \DateTime())->modify('+1 hour');
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_inizio', $inizio->format('Y-m-d H:i'));
    $this->em->getRepository('App\Entity\Configurazione')->setParametro('manutenzione_fine', $fine->format('Y-m-d H:i'));
    // esegue
    $this->testedTrait->controllaManutenzione($utente);
    // controlla
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test sui profili attivi di utente con codice fiscale vuoto.
   *
   */
  public function testProfiliSenzaCodiceFiscale(): void {
    // init
    $this->logs = [];
    $ata = $this->getReference('ata_A');
    $genitore1 = $this->getReference('genitore1_1A_1');
    $genitore2 = $this->getReference('genitore2_1A_1');
    $ata->setCodiceFiscale('')->setNome('Mario')->setCognome('Rossi');
    $genitore1->setCodiceFiscale('')->setNome('Mario')->setCognome('Rossi');
    $genitore2->setCodiceFiscale('')->setNome('Mario')->setCognome('Rossi');
    $this->em->flush();
    // esegue
    $u = $this->testedTrait->controllaProfili($ata);
    // controlla
    $this->assertSame([], $u->getListaProfili());
    $this->assertSame($ata, $u);
    $this->assertCount(0, $this->logs);
  }

  /**
   * Test di utente senza profili attivi.
   *
   */
  public function testProfiliNessunoAttivo(): void {
    // init
    $this->logs = [];
    $ata = $this->getReference('ata_A');
    $ata->setAbilitato(false)->setCodiceFiscale('CODICEFISCALE');;
    $this->em->flush();
    // esegue
    try {
      $exception = null;
      $u = $this->testedTrait->controllaProfili($ata);
    } catch (CustomUserMessageAuthenticationException $e) {
      $exception = $e->getMessage();
    }
    // controlla
    $this->assertSame('exception.invalid_user', $exception);
    $this->assertCount(1, $this->logs);
    $this->assertSame(['username' => $ata->getUsername()], $this->logs['error'][0][1]);
  }

  /**
   * Test di utente con profilo unico.
   *
   * @dataProvider profiliProvider
   */
  public function testProfili(array $utenti, string $risposta, array $lista): void {
    // init
    $this->logs = [];
    $utentiObj = [];
    foreach ($utenti as $utente) {
      $utenteObj = $this->getReference($utente);
      $utenteObj->setCodiceFiscale('CODICEFISCALE')->setNome('Mario')->setCognome('Rossi');
      $utentiObj[] = $utenteObj;
    }
    $this->em->flush();
    // esegue
    $u = $this->testedTrait->controllaProfili($utentiObj[0]);
    // controlla
    $this->assertCount(0, $this->logs);
    $this->assertSame($this->getReference($risposta), $u);
    $listaProfili = [];
    foreach ($u->getListaProfili() as $key => $array) {
      $listaProfili = array_merge($listaProfili, array_values($array));
    }
    $listaAttesa = array_map(fn($p) => $this->getReference($p)->getId(), $lista);
    $this->assertSame(sort($listaAttesa), sort($listaProfili));
  }

  /**
   * Dati per test dei profili.
   *
   */
  public function profiliProvider(): array {
    return [
      // profilo unico
      [['amministratore'], 'amministratore', []],
      [['ata_A'], 'ata_A', []],
      [['docente_curricolare_1'], 'docente_curricolare_1', []],
      [['staff_1'], 'staff_1', []],
      [['preside'], 'preside', []],
      [['genitore1_1A_1'], 'genitore1_1A_1', []],
      [['alunno_2A_1'], 'alunno_2A_1', []],
      [['utente_1'], 'utente_1', []],
      // profilo multiplo
      [['genitore1_1A_1', 'genitore1_3A_1'], 'genitore1_1A_1', ['genitore1_1A_1', 'genitore1_3A_1']],
      [['genitore1_1A_1', 'genitore1_2A_1', 'genitore1_3A_1'], 'genitore1_1A_1', ['genitore1_1A_1', 'genitore1_2A_1', 'genitore1_3A_1']],
      [['docente_curricolare_1', 'genitore1_1A_1'], 'docente_curricolare_1', ['docente_curricolare_1', 'genitore1_1A_1']],
      [['docente_curricolare_1', 'genitore1_1A_1', 'genitore1_2A_1'], 'docente_curricolare_1', ['docente_curricolare_1', 'genitore1_1A_1', 'genitore1_2A_1']],
      [['staff_1', 'genitore1_1A_1'], 'staff_1', ['staff_1', 'genitore1_1A_1']],
      [['preside', 'genitore1_1A_1'], 'preside', ['preside', 'genitore1_1A_1']],
      [['ata_A', 'genitore1_1A_1'], 'ata_A', ['ata_A', 'genitore1_1A_1']],
      [['genitore1_1A_1', 'amministratore'], 'genitore1_1A_1', []],
      [['genitore1_1A_1', 'ata_A'], 'genitore1_1A_1', []],
      [['genitore1_1A_1', 'docente_curricolare_1'], 'genitore1_1A_1', []],
      [['genitore1_1A_1', 'staff_1'], 'genitore1_1A_1', []],
      [['genitore1_1A_1', 'preside'], 'genitore1_1A_1', []],
      [['genitore1_1A_1', 'utente_1', ], 'genitore1_1A_1', []],
    ];
  }

}
