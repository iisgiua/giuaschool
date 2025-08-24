<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Repository;

use App\Entity\Alunno;
use App\Repository\AlunnoRepository;
use App\Tests\DatabaseTestCase;


/**
 * Unit test per il repository Alunno
 *
 * @author Antonello Dessì
 */
class AlunnoRepositoryTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE ====================

  /**
   * @var AlunnoRepository|null $repo Repository da testare
   */
  private ?AlunnoRepository $repo = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AlunnoFixtures'];
    // esegue il setup standard
    parent::setUp();
    // inizializza repository
    $this->repo = $this->em->getRepository(Alunno::class);
  }

  /**
   * findAllEnabled
   */
  public function testFindAllEnabled(): void {
    $criteri = ['nome' => '', 'cognome' => '', 'classe' => null];
    // senza filtri
    $res = $this->repo->findAllEnabled($criteri, 1, 20);

    $this->assertTrue($res->count() > 0);
  }


}
