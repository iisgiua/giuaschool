<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Repository;

use App\Entity\Docente;
use App\Repository\DocenteRepository;
use App\Tests\DatabaseTestCase;


/**
 * Unit test per il repository Docente
 *
 * @author Antonello Dessì
 */
class DocenteRepositoryTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE ====================

  /**
   * @var DocenteRepository|null $repo Repository da testare
   */
  private ?DocenteRepository $repo = null;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['DocenteFixtures'];
    // esegue il setup standard
    parent::setUp();
    // inizializza repository
    $this->repo = $this->em->getRepository(Docente::class);
  }

  /**
   * listaDocenti
   */
  public function testListaDocenti(): void {
    // docente di riferimento
    $docente = $this->em->createQuery('SELECT d FROM App\Entity\Docente d '
      .'WHERE d.abilitato=1')->setMaxResults(1)->getOneOrNullResult();
    $this->assertNotNull($docente);
    // nome con caratteri da codificare
    $docente->setCognome('Bianchi<b>"x"</b>');
    $this->em->flush();
    // legge lista
    $res = $this->repo->listaDocenti([$docente->getId()], 'gs-docenti-');
    // controlla che il nome sia codificato e che l'HTML sia integro
    $this->assertStringContainsString('gs-docenti-'.$docente->getId(), $res);
    $this->assertStringContainsString('&lt;b&gt;', $res);
    $this->assertStringContainsString('&quot;x&quot;', $res);
    $this->assertStringNotContainsString('<b>', $res);
  }


}
