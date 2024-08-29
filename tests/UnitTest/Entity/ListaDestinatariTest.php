<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità ListaDestinatari
 *
 * @author Antonello Dessì
 */
class ListaDestinatariTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = \App\Entity\ListaDestinatari::class;
    // campi da testare
    $this->fields = ['dsga', 'ata', 'docenti', 'filtroDocenti', 'coordinatori', 'filtroCoordinatori', 'staff', 'genitori', 'filtroGenitori', 'alunni', 'filtroAlunni'];
    $this->noStoredFields = ['sedi'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_lista_destinatari' => ['id', 'creato', 'modificato', 'dsga', 'ata', 'docenti', 'filtro_docenti', 'coordinatori', 'filtro_coordinatori', 'staff', 'genitori', 'filtro_genitori', 'alunni', 'filtro_alunni'],
      'gs_sede' => '*'];
    // SQL write
    $this->canWrite = ['gs_lista_destinatari' => ['id', 'creato', 'modificato', 'dsga', 'ata', 'docenti', 'filtro_docenti', 'coordinatori', 'filtro_coordinatori', 'staff', 'genitori', 'filtro_genitori', 'alunni', 'filtro_alunni']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
  }

  /**
   * Test sull'inizializzazione degli attributi.
   * Controlla errore "Typed property must not be accessed before initialization"
   *
   */
  public function testInitialized(): void {
    // crea nuovo oggetto
    $obj = new $this->entity();
    // verifica inizializzazione
    foreach (array_merge($this->fields, $this->noStoredFields, $this->generatedFields) as $field) {
      $this->assertTrue($obj->{'get'.ucfirst((string) $field)}() === null || $obj->{'get'.ucfirst((string) $field)}() !== null,
        $this->entity.' - Initializated');
    }
  }

  /**
   * Test sui metodi getter/setter degli attributi, con memorizzazione su database.
   * Sono esclusi gli attributi ereditati.
   *
   */
  public function testProperties() {
    // crea nuovi oggetti
    for ($i = 0; $i < 5; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          ($field == 'dsga' ? $this->faker->boolean() :
          ($field == 'ata' ? $this->faker->boolean() :
          ($field == 'docenti' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroDocenti' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'coordinatori' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroCoordinatori' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'staff' ? $this->faker->boolean() :
          ($field == 'genitori' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroGenitori' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'alunni' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroAlunni' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          null)))))))))));
        $o[$i]->{'set'.ucfirst((string) $field)}($data[$i][$field]);
      }
      foreach ($this->generatedFields as $field) {
        $this->assertEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Pre-insert');
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst((string) $field)}(), $this->entity.'::get'.ucfirst((string) $field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst((string) $field)}();
      }
      // controlla dati dopo l'aggiornamento
      sleep(1);
      $data[$i]['filtroDocenti'] = [$this->faker->text()];
      $o[$i]->setFiltroDocenti($data[$i]['filtroDocenti']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst((string) $field)}(),
          $this->entity.'::get'.ucfirst((string) $field));
      }
    }
    // controlla metodi setter per attributi generati
    $rc = new \ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst((string) $field)), $this->entity.'::set'.ucfirst((string) $field).' - Setter for generated property');
    }
  }

  /**
   * Test altri metodi
   */
  public function testMethods() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // toString
    $this->assertSame('Destinatari: '.($existent->getDsga() ? 'DSGA ' : '').($existent->getAta() ? 'ATA ' : '').
      ($existent->getDocenti() != 'N' ? 'Docenti ' : '').($existent->getCoordinatori() != 'N' ? 'Coordinatori ' : '').
      ($existent->getStaff() ? 'Staff ' : '').($existent->getGenitori() != 'N' ? 'Genitori ' : '').
      ($existent->getAlunni() != 'N' ? 'Alunni ' : ''), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'sedi' => array_map(fn($ogg) => $ogg->getId(), $existent->getSedi()->toArray()),
      'dsga' => $existent->getDsga(),
      'ata' => $existent->getAta(),
      'docenti' => $existent->getDocenti(),
      'filtroDocenti' => $existent->getFiltroDocenti(),
      'coordinatori' => $existent->getCoordinatori(),
      'filtroCoordinatori' => $existent->getFiltroCoordinatori(),
      'staff' => $existent->getStaff(),
      'genitori' => $existent->getGenitori(),
      'filtroGenitori' => $existent->getFiltroGenitori(),
      'alunni' => $existent->getAlunni(),
      'filtroAlunni' => $existent->getFiltroAlunni()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $dt['genitori'] = 'C';
    $dt['filtroGenitori'] = [1, 2];
    $existent->setGenitori('C')->setFiltroGenitori([1, 2]);
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $sedi = new \Doctrine\Common\Collections\ArrayCollection([$this->getReference("sede_1"), $this->getReference("sede_2")]);
    $existent->setSedi($sedi);
    $dt['sedi'] = array_map(fn($ogg) => $ogg->getId(), $sedi->toArray());
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    // addSedi
    $items = $existent->getSedi()->toArray();
    $item = new \App\Entity\Sede();
    $existent->addSedi($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::addSedi');
    $existent->addSedi($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::addSedi');
    // removeSedi
    $items = $existent->getSedi()->toArray();
    if (count($items) == 0) {
      $item = new \App\Entity\Sede();
    } else {
      $item = $items[0];
    }
    $existent->removeSedi($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::removeSedi');
    $existent->removeSedi($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::removeSedi');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // sedi
    $property = $this->getPrivateProperty(\App\Entity\ListaDestinatari::class, 'sedi');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Sedi - NOT BLANK');
    $existent->setSedi(new \Doctrine\Common\Collections\ArrayCollection([$this->getReference("sede_1")]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sedi - VALID NOT BLANK');
    // docenti
    $existent->setDocenti('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Docenti - CHOICE');
    $existent->setDocenti('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docenti - VALID CHOICE');
    // coordinatori
    $existent->setCoordinatori('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Coordinatori - CHOICE');
    $existent->setCoordinatori('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Coordinatori - VALID CHOICE');
    // genitori
    $existent->setGenitori('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Genitori - CHOICE');
    $existent->setGenitori('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Genitori - VALID CHOICE');
    // alunni
    $existent->setAlunni('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Alunni - CHOICE');
    $existent->setAlunni('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunni - VALID CHOICE');
  }

}
