<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Raggruppamento;
use ReflectionClass;
use Doctrine\Common\Collections\ArrayCollection;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità GruppoClasse
 *
 * @author Antonello Dessì
 */
class RaggruppamentoTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = Raggruppamento::class;
    // campi da testare
    $this->fields = ['nome'];
    $this->noStoredFields = ['alunni'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_raggruppamento' => ['id', 'creato', 'modificato', 'nome'],
      'gs_utente' => '*'];
    // SQL write
    $this->canWrite = ['gs_raggruppamento' => ['id', 'creato', 'modificato', 'nome'],
      'gs_raggruppamento_alunno' => '*'];
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
          ($field == 'nome' ? $this->faker->passthrough(substr($this->faker->text(), 0, 64)) :
          null);
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
      $data[$i]['nome'] = substr($this->faker->text(), 0, 64);
      $o[$i]->setNome($data[$i]['nome']);
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
    $rc = new ReflectionClass($this->entity);
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
    // addAlunni
    $items = $existent->getAlunni()->toArray();
    $item = $this->getReference("alunno_1A_1");
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
    $this->assertSame($existent->getNome(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // nome
    $property = $this->getPrivateProperty(Raggruppamento::class, 'nome');
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
    // alunni
    $property = $this->getPrivateProperty(Raggruppamento::class, 'alunni');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::alunni - NOT BLANK');
    $existent->setAlunni(new ArrayCollection([$this->getReference("alunno_1A_1")]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::alunni - VALID NOT BLANK');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique nome
    $nomeSaved = $objects[1]->getNome();
    $objects[1]->setNome($objects[0]->getNome());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::nome - UNIQUE');
    $objects[1]->setNome($nomeSaved);
    $err = $this->val->validate($objects[1]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
  }

}
