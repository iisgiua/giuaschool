<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Annotazione;
use App\Entity\Avviso;
use App\Tests\EntityTestCase;
use DateTime;
use ReflectionClass;


/**
 * Unit test dell'entità Avviso
 *
 * @author Antonello Dessì
 */
class AvvisoTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Avviso::class;
    // campi da testare
    $this->fields = ['autore', 'cattedra', 'materia', 'tipo', 'cifrato', 'firma', 'stato', 'titolo', 'data',
      'anno', 'speciali', 'ata', 'coordinatori', 'filtroCoordinatori', 'docenti', 'filtroDocenti', 'genitori',
      'filtroGenitori', 'rappresentantiGenitori', 'filtroRappresentantiGenitori', 'alunni', 'filtroAlunni',
      'rappresentantiAlunni', 'filtroRappresentantiAlunni', 'esterni', 'testo', 'sostituzioni'];
    $this->noStoredFields = ['allegati', 'annotazioni', 'sedi'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_comunicazione' => ['id', 'autore_id', 'materia_id', 'classe_id', 'alunno_id', 'cattedra_id', 'creato', 'modificato', 'tipo', 'cifrato', 'firma', 'stato', 'titolo', 'data', 'anno', 'speciali', 'ata', 'coordinatori', 'filtro_coordinatori', 'docenti', 'filtro_docenti', 'genitori', 'filtro_genitori', 'rappresentanti_genitori', 'filtro_rappresentanti_genitori', 'alunni', 'filtro_alunni', 'rappresentanti_alunni', 'filtro_rappresentanti_alunni', 'esterni', 'categoria', 'numero', 'testo', 'sostituzioni'],
      'gs_annotazione' => '*',
      'gs_allegato' => '*',
      'gs_sede' => '*'];
    // SQL write
    $this->canWrite = ['gs_comunicazione' => ['id', 'autore_id', 'materia_id', 'classe_id', 'alunno_id', 'cattedra_id', 'creato', 'modificato', 'tipo', 'cifrato', 'firma', 'stato', 'titolo', 'data', 'anno', 'speciali', 'ata', 'coordinatori', 'filtro_coordinatori', 'docenti', 'filtro_docenti', 'genitori', 'filtro_genitori', 'rappresentanti_genitori', 'filtro_rappresentanti_genitori', 'alunni', 'filtro_alunni', 'rappresentanti_alunni', 'filtro_rappresentanti_alunni', 'esterni', 'categoria', 'numero', 'testo', 'sostituzioni']];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
    // esegue il setup predefinito
    parent::setUp();
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
          ($field == 'autore' ? $this->getReference("docente_curricolare_1") :
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'cifrato' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'firma' ? $this->faker->boolean() :
          ($field == 'stato' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1))  :
          ($field == 'titolo' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'data' ? $this->faker->dateTime() :
          ($field == 'anno' ? $this->faker->randomNumber(4, false) :
          ($field == 'speciali' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 5)) :
          ($field == 'ata' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 3)) :
          ($field == 'coordinatori' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroCoordinatori' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'docenti' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroDocenti' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'genitori' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroGenitori' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'rappresentantiGenitori' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroRappresentantiGenitori' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'alunni' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroAlunni' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'rappresentantiAlunni' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroRappresentantiAlunni' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'esterni' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          ($field == 'cattedra' ? $this->faker->optional($weight = 50, $default = null)->passthrough($this->getReference("cattedra_curricolare_1")) :
          ($field == 'materia' ? $this->faker->optional($weight = 50, $default = null)->passthrough($this->getReference("materia_curricolare_1")) :
          ($field == 'testo' ? $this->faker->text() :
          ($field == 'sostituzioni' ? $this->faker->optional($weight = 50, $default = [])->passthrough($this->faker->sentences($i)) :
          null)))))))))))))))))))))))))));
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
      $data[$i]['testo'] = $this->faker->text();
      $o[$i]->setTesto($data[$i]['testo']);
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
    // addAnnotazione
    $items = $existent->getAnnotazioni()->toArray();
    $item = new Annotazione();
    $item->setData(new DateTime());
    $existent->addAnnotazione($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getAnnotazioni()->toArray()), $this->entity.'::addAnnotazioni');
    $existent->addAnnotazione($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getAnnotazioni()->toArray()), $this->entity.'::addAnnotazioni');
    // removeAnnotazione
    $items = $existent->getAnnotazioni()->toArray();
    $item = $items[0];
    $existent->removeAnnotazione($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getAnnotazioni()->toArray()), $this->entity.'::removeAnnotazioni');
    $existent->removeAnnotazione($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getAnnotazioni()->toArray()), $this->entity.'::removeAnnotazioni');
    // testoPersonalizzato
    $existent->setTesto('Ciao {nome} {cognome}, benvenuto!');
    $existent->setSostituzioni(['{nome}' => 'Mario', '{cognome}' => 'Rossi']);
    $val = $existent->testoPersonalizzato();
    $this->assertSame('Ciao Mario Rossi, benvenuto!', $val, $this->entity.'::testoPersonalizzato');
    // toString
    $this->assertSame('Avviso "'.$existent->getTitolo().'"', (string) $existent, $this->entity.'::toString');
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
    $existent->setTipo('U');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID CHOICE');
    // testo
    $property = $this->getPrivateProperty(Avviso::class, 'testo');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Testo - NOT BLANK');
    $existent->setTesto('');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Testo - NOT BLANK');
    $existent->setTesto('1234');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Testo - VALID NOT BLANK');
  }

}
