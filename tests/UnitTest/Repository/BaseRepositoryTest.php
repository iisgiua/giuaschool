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
use Doctrine\ORM\Query;


/**
 * Unit test per il repository Base
 *
 * @author Antonello Dessì
 */
class BaseRepositoryTest extends DatabaseTestCase {


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
   * Test della funzione paginazione
   */
  public function testPaginazione(): void {
    // init
    $count = $this->em->getRepository(Alunno::class)->createQueryBuilder('a')
      ->select("COUNT(a.id)")
      ->getQuery()
      ->getSingleScalarResult();
    $query = new Query($this->em);
    // pagina iniziale
    $query->setDQL("SELECT a FROM App\Entity\Alunno AS a");
    $res = $this->repo->paginazione($query, 1);
    $this->assertSame(0, $res['lista']->getQuery()->getFirstResult());
    $this->assertSame(20, $res['lista']->getQuery()->getMaxResults());
    $this->assertSame(ceil($count / 20.0), $res['maxPagine']);
    // pagina successiva
    $query->setDQL("SELECT a FROM App\Entity\Alunno AS a");
    $res = $this->repo->paginazione($query, 2);
    $this->assertSame(20, $res['lista']->getQuery()->getFirstResult());
    $this->assertSame(20, $res['lista']->getQuery()->getMaxResults());
    $this->assertSame(ceil($count / 20.0), $res['maxPagine']);
  }

}
