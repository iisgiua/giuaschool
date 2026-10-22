<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Util;

use App\Util\RegistroUtil;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * Unit test per le informazioni sui periodi dell'anno scolastico.
 */
class RegistroUtilTest extends TestCase {


  /**
   * Crea un'istanza di RegistroUtil con la configurazione indicata.
   */
  private function creaUtil(array $config): RegistroUtil {
    $session = new Session(new MockArraySessionStorage());
    foreach ($config as $chiave => $valore) {
      $session->set('/CONFIG/SCUOLA/'.$chiave, $valore);
    }
    $request = Request::create('/');
    $request->setSession($session);
    $stack = new RequestStack();
    $stack->push($request);
    return new RegistroUtil(
      $this->createMock(RouterInterface::class),
      $this->createMock(EntityManagerInterface::class),
      $this->createMock(TranslatorInterface::class),
      $stack);
  }

  /**
   * I due periodi sono calcolati correttamente.
   */
  public function testInfoPeriodiDuePeriodi(): void {
    $dati = $this->creaUtil([
      'anno_inizio' => '2026-09-15',
      'anno_fine' => '2027-06-10',
      'periodo1_nome' => 'Primo Quadrimestre',
      'periodo1_fine' => '2027-01-31',
      'periodo2_nome' => 'Secondo Quadrimestre',
      'periodo2_fine' => '2027-06-10',
      'periodo3_nome' => '',
    ])->infoPeriodi();

    $this->assertSame('P', $dati[1]['scrutinio']);
    $this->assertSame('2027-02-01', $dati[2]['inizio']);
    $this->assertSame('F', $dati[2]['scrutinio']);
    $this->assertSame('', $dati[3]['scrutinio']);
    $this->assertSame('2027-06-10', $dati[3]['fine']);
  }

  /**
   * Una data non valida non deve provocare un errore fatale.
   */
  public function testInfoPeriodiDataNonValida(): void {
    $dati = $this->creaUtil([
      'anno_inizio' => '2026-09-15',
      'anno_fine' => '2027-06-10',
      'periodo1_fine' => '0000-00-00',
      'periodo2_fine' => '2027-06-10',
      'periodo3_nome' => '',
    ])->infoPeriodi();

    $this->assertSame('', $dati[2]['inizio']);
    $this->assertSame('F', $dati[2]['scrutinio']);
    $this->assertSame('', $dati[3]['scrutinio']);
  }

  /**
   * Il terzo periodo è calcolato correttamente.
   */
  public function testInfoPeriodiTrePeriodi(): void {
    $dati = $this->creaUtil([
      'anno_inizio' => '2026-09-15',
      'anno_fine' => '2027-06-10',
      'periodo1_fine' => '2026-12-23',
      'periodo2_fine' => '2027-03-31',
      'periodo3_nome' => 'Terzo Trimestre',
    ])->infoPeriodi();

    $this->assertSame('S', $dati[2]['scrutinio']);
    $this->assertSame('2027-04-01', $dati[3]['inizio']);
    $this->assertSame('F', $dati[3]['scrutinio']);
  }


}
