<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\MessageHandler;

use App\Repository\BaseRepository;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;


/**
 * Unit test per il repository Base
 *
 * @author Antonello Dessì
 */
class BaseRepositoryTest extends DatabaseTestCase {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AlunnoFixtures'];
    // esegue il setup standard
    parent::setUp();
  }

  /**
   * Test della funzione paginazione
   *
   */
  public function testPaginazione(): void {
    // init
    $count = $this->em->getRepository('App\Entity\Alunno')->createQueryBuilder('a')
      ->select("COUNT(a.id)")
      ->getQuery()
      ->getSingleScalarResult();
    $repository = $this->em->getRepository('App\Entity\Alunno');
    $query = new Query($this->em);
    // pagina iniziale
    $query->setDQL("SELECT a FROM App\Entity\Alunno AS a");
    $res = $repository->paginazione($query, 1);
    $this->assertSame(0, $res['lista']->getQuery()->getFirstResult());
    $this->assertSame(20, $res['lista']->getQuery()->getMaxResults());
    $this->assertSame(ceil($count / 20.0), $res['maxPagine']);
    // pagina successiva
    $query->setDQL("SELECT a FROM App\Entity\Alunno AS a");
    $res = $repository->paginazione($query, 2);
    $this->assertSame(20, $res['lista']->getQuery()->getFirstResult());
    $this->assertSame(20, $res['lista']->getQuery()->getMaxResults());
    $this->assertSame(ceil($count / 20.0), $res['maxPagine']);
  }

}
