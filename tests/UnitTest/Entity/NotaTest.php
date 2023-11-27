<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Nota
 *
 * @author Antonello Dessì
 */
class NotaTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Nota';
    // campi da testare
    $this->fields = ['tipo', 'data', 'testo', 'provvedimento', 'classe', 'docente', 'docenteProvvedimento'];
    $this->noStoredFields = ['alunni'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_nota' => ['id', 'creato', 'modificato', 'tipo', 'data', 'testo', 'provvedimento', 'classe_id', 'docente_id', 'docente_provvedimento_id'],
      'gs_classe' => '*'];
    // SQL write
    $this->canWrite = ['gs_nota' => ['id', 'creato', 'modificato', 'tipo', 'data', 'testo', 'provvedimento', 'classe_id', 'docente_id', 'docente_provvedimento_id']];
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
      $this->assertTrue($obj->{'get'.ucfirst($field)}() === null || $obj->{'get'.ucfirst($field)}() !== null,
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
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'data' ? $this->faker->dateTime() :
          ($field == 'testo' ? $this->faker->text() :
          ($field == 'provvedimento' ? $this->faker->optional($weight = 50, $default = '')->text() :
          ($field == 'classe' ? $this->getReference("classe_1") :
          ($field == 'docente' ? $this->getReference("docente_1") :
          ($field == 'docenteProvvedimento' ? $this->getReference("docente_1") :
          null)))))));
        $o[$i]->{'set'.ucfirst($field)}($data[$i][$field]);
      }
      foreach ($this->generatedFields as $field) {
        $this->assertEmpty($o[$i]->{'get'.ucfirst($field)}(), $this->entity.'::get'.ucfirst($field).' - Pre-insert');
      }
      // memorizza su db: controlla dati dopo l'inserimento
      $this->em->persist($o[$i]);
      $this->em->flush();
      foreach ($this->generatedFields as $field) {
        $this->assertNotEmpty($o[$i]->{'get'.ucfirst($field)}(), $this->entity.'::get'.ucfirst($field).' - Post-insert');
        $data[$i][$field] = $o[$i]->{'get'.ucfirst($field)}();
      }
      // controlla dati dopo l'aggiornamento
      sleep(1);
      $data[$i]['testo'] = $this->faker->text();
      $o[$i]->setTesto($data[$i]['testo']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
          $this->entity.'::get'.ucfirst($field));
      }
    }
    // controlla metodi setter per attributi generati
    $rc = new \ReflectionClass($this->entity);
    foreach ($this->generatedFields as $field) {
      $this->assertFalse($rc->hasMethod('set'.ucfirst($field)), $this->entity.'::set'.ucfirst($field).' - Setter for generated property');
    }
  }

  /**
   * Test altri metodi
   */
  public function testMethods() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    // addAlunni
    $existent->setAlunni(new \Doctrine\Common\Collections\ArrayCollection());
    $item1 = $this->getReference('alunno_1');
    $existent->addAlunni($item1);
    $item2 = $this->getReference('alunno_2');
    $existent->addAlunni($item2);
    $this->assertSame([$item1, $item2], array_values($existent->getAlunni()->toArray()), $this->entity.'::addAlunni');
    // removeAlunni
    $existent->removeAlunni($item2);
    $existent->removeAlunni($item2);
    $this->assertSame([$item1], array_values($existent->getAlunni()->toArray()), $this->entity.'::removeAlunni');
    // toString
    $this->assertSame($existent->getData()->format('d/m/Y').' '.$existent->getClasse().': '.$existent->getTesto(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // tipo
    $existent->setTipo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Tipo - CHOICE');
    $existent->setTipo('C');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID CHOICE');
    // data
    $property = $this->getPrivateProperty('App\Entity\Nota', 'data');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Data - NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID TYPE');
    // testo
    $property = $this->getPrivateProperty('App\Entity\Nota', 'testo');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Testo - NOT BLANK');
    $existent->setTesto($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Testo - VALID NOT BLANK');
    // classe
    $property = $this->getPrivateProperty('App\Entity\Nota', 'classe');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Classe - NOT BLANK');
    $existent->setClasse($this->getReference("classe_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Classe - VALID NOT BLANK');
    // docente
    $property = $this->getPrivateProperty('App\Entity\Nota', 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($this->getReference("docente_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
    // docenteProvvedimento
    $existent->setDocenteProvvedimento(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::DocenteProvvedimento - VALID NULL');
  }

}
