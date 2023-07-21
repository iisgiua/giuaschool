<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità GruppoClasse
 *
 * @author Antonello Dessì
 */
class GruppoClasseTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\GruppoClasse';
    // campi da testare
    $this->fields = ['anno', 'sezione', 'oreSettimanali', 'sede', 'corso', 'coordinatore', 'segretario', 'classe', 'nome'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = 'EntityTestFixtures';
    // SQL read
    $this->canRead = ['gs_classe' => ['id', 'creato', 'modificato', 'anno', 'sezione', 'ore_settimanali', 'sede_id', 'corso_id', 'coordinatore_id', 'segretario_id', 'tipo', 'classe_id', 'nome'],
      'gs_utente' => '*'];
    // SQL write
    $this->canWrite = ['gs_classe' => ['id', 'creato', 'modificato', 'anno', 'sezione', 'ore_settimanali', 'sede_id', 'corso_id', 'coordinatore_id', 'segretario_id', 'tipo', 'classe_id', 'nome'],
      'gs_gruppo_classe_alunno' => '*'];
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
          ($field == 'anno' ? $this->faker->randomNumber(4, false) :
          ($field == 'sezione' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'oreSettimanali' ? $this->faker->randomNumber(4, false) :
          ($field == 'sede' ? $this->getReference("sede_1") :
          ($field == 'corso' ? $this->getReference("corso_1") :
          ($field == 'coordinatore' ? $this->getReference("docente_1") :
          ($field == 'segretario' ? $this->getReference("docente_2") :
          ($field == 'nome' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          ($field == 'classe' ? $this->getReference("classe_3") :
          null)))))))));
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
      $data[$i]['anno'] = $this->faker->randomNumber(4, false);
      $o[$i]->setAnno($data[$i]['anno']);
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
    $items = $existent->getAlunni()->toArray();
    $item = $this->getReference("alunno_1");
    $existent->addAlunni($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getAlunni()->toArray()), $this->entity.'::addAlunni');
    $existent->addAlunni($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getAlunni()->toArray()), $this->entity.'::addAlunni');
    // removeAlunni
    $items = $existent->getAlunni()->toArray();
    $item = $items[0];
    $existent->removeAlunni($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getAlunni()->toArray()), $this->entity.'::removeAlunni');
    $existent->removeAlunni($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getAlunni()->toArray()), $this->entity.'::removeAlunni');
    // toString
    $this->assertSame($existent->getAnno().'ª '.$existent->getSezione().'-'.$existent->getNome(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // nome
    $property = $this->getPrivateProperty('App\Entity\GruppoClasse', 'nome');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::nome - NOT BLANK');
    $existent->setNome($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID NOT BLANK');
    $existent->setNome(str_repeat('*', 65));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::nome - MAX LENGTH');
    $existent->setNome(str_repeat('*', 64));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::nome - VALID MAX LENGTH');
    // classe
    $existent->setClasse(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::classe - VALID NULL');
    // alunni
    $property = $this->getPrivateProperty('\App\Entity\GruppoClasse', 'alunni');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::alunni - NOT BLANK');
    $existent->setAlunni(new \Doctrine\Common\Collections\ArrayCollection([$this->getReference("alunno_1")]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::alunni - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique anno-sezione-nome
    $annoSaved = $objects[1]->getAnno();
    $objects[1]->setAnno($objects[0]->getAnno());
    $sezioneSaved = $objects[1]->getSezione();
    $objects[1]->setSezione($objects[0]->getSezione());
    $nomeSaved = $objects[1]->getNome();
    $objects[1]->setNome($objects[0]->getNome());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::anno-sezione-nome - UNIQUE');
    $objects[1]->setAnno($annoSaved);
    $objects[1]->setSezione($sezioneSaved);
    $objects[1]->setNome($nomeSaved);
  }

}
