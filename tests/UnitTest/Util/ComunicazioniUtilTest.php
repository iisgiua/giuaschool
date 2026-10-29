<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Util;

use App\Entity\Annotazione;
use App\Entity\Avviso;
use App\Entity\Classe;
use App\Entity\Docente;
use App\Entity\Preside;
use App\Entity\Sede;
use App\Util\ComunicazioniUtil;
use App\Util\PdfManager;
use App\Util\RegistroUtil;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;


/**
 * Unit test per la validazione degli avvisi.
 */
class ComunicazioniUtilTest extends TestCase {

  /**
   * Restituisce l'utilità da testare.
   *
   * @return ComunicazioniUtil Funzioni di utilità per le comunicazioni
   */
  private function creaUtil(): ComunicazioniUtil {
    return new ComunicazioniUtil($this->createMock(EntityManagerInterface::class),
      $this->createMock(PdfManager::class), '', '', '');
  }

  /**
   * Un avviso con annotazione creata da un altro docente può essere validato
   * dal preside: il controllo sulla cancellazione dell'annotazione non deve
   * bloccare la modifica dell'avviso.
   */
  public function testValidaAvvisoClassiConAnnotazioneDiAltroDocente(): void {
    // dati di prova
    $sede = (new Sede())->setNome('Sede')->setNomeBreve('S');
    $docente = (new Docente())->setUsername('docente');
    $classe = (new Classe())->setAnno(1)->setSezione('A');
    $annotazione = (new Annotazione())
      ->setData(new DateTime('2026-10-01'))
      ->setTesto('Annotazione sul registro')
      ->setDocente($docente)
      ->setClasse($classe);
    $avviso = (new Avviso())
      ->setTipo('C')
      ->setData(new DateTime('2026-10-25'))
      ->setTitolo('Avviso di prova')
      ->setDocenti('T')
      ->setFiltroDocenti([1]);
    $avviso->addSede($sede);
    $avviso->addAnnotazione($annotazione);
    // la cancellazione dell'annotazione non è permessa al preside,
    // ma la modifica dell'avviso sì
    $reg = $this->createMock(RegistroUtil::class);
    $reg->method('controlloData')->willReturn(null);
    $reg->method('azioneAnnotazione')->willReturnCallback(
      fn(string $azione): bool => $azione !== 'delete');
    // la validazione non deve restituire errori
    $this->assertNull($this->creaUtil()->validaAvvisoClassi($avviso, new Preside(), $reg));
  }

}
