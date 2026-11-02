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

  /**
   * listaAlunni
   */
  public function testListaAlunni(): void {
    // alunno di riferimento (con classe, come richiesto dalla query)
    $alunno = $this->em->createQuery('SELECT a FROM App\Entity\Alunno a JOIN a.classe c '
      .'WHERE a.abilitato=1')->setMaxResults(1)->getOneOrNullResult();
    $this->assertNotNull($alunno);
    // nome con caratteri da codificare
    $alunno->setCognome('Rossi<b>"x"</b>');
    $this->em->flush();
    // legge lista
    $res = $this->repo->listaAlunni([$alunno->getId()], 'gs-filtro-');
    // controlla che il nome sia codificato e che l'HTML sia integro
    $this->assertStringContainsString('gs-filtro-'.$alunno->getId(), $res);
    $this->assertStringContainsString('&lt;b&gt;', $res);
    $this->assertStringContainsString('&quot;x&quot;', $res);
    $this->assertStringNotContainsString('<b>', $res);
    // controlla i dati di classe e nascita
    $this->assertMatchesRegularExpression('/\(\d{2}\/\d{2}\/\d{4}\) \d+ª /', $res);
  }


}
