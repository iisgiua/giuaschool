<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità RichiestaColloquio
 *
 * @author Antonello Dessì
 */
class RichiestaColloquioTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\RichiestaColloquio';
    // campi da testare
    $this->fields = ['appuntamento', 'colloquio', 'alunno', 'genitore', 'genitoreAnnulla', 'stato', 'messaggio'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_richiesta_colloquio' => ['id', 'creato', 'modificato', 'appuntamento', 'colloquio_id', 'alunno_id', 'genitore_id', 'genitore_annulla_id', 'stato', 'messaggio'],
      'gs_colloquio' => '*'];
    // SQL write
    $this->canWrite = ['gs_richiesta_colloquio' => ['id', 'creato', 'modificato', 'appuntamento', 'colloquio_id', 'alunno_id', 'genitore_id', 'genitore_annulla_id', 'stato', 'messaggio']];
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
          ($field == 'appuntamento' ? $this->faker->dateTime() :
          ($field == 'colloquio' ? $this->getReference("colloquio_1") :
          ($field == 'alunno' ? $this->getReference("alunno_1A_1") :
          ($field == 'genitore' ? $this->getReference("genitore1_1A_1") :
          ($field == 'genitoreAnnulla' ? $this->getReference("genitore1_1A_1") :
          ($field == 'stato' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'messaggio' ? $this->faker->optional($weight = 50, $default = '')->text() :
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
      $data[$i]['appuntamento'] = $this->faker->dateTime();
      $o[$i]->setAppuntamento($data[$i]['appuntamento']);
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
    // toString
    $this->assertSame($existent->getColloquio().', '.$existent->getAppuntamento()->format('H:i'), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'appuntamento' => $existent->getAppuntamento()->format('H:i'),
      'colloquio' => $existent->getColloquio()->getId(),
      'alunno' => $existent->getAlunno()->getId(),
      'genitore' => $existent->getGenitore()->getId(),
      'genitoreAnnulla' => $existent->getGenitoreAnnulla() ? $existent->getGenitoreAnnulla()->getId() : '',
      'stato' => $existent->getStato(),
      'messaggio' => $existent->getMessaggio()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // appuntamento
    $property = $this->getPrivateProperty('App\Entity\RichiestaColloquio', 'appuntamento');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Appuntamento - NOT BLANK');
    $existent->setAppuntamento(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Appuntamento - VALID NOT BLANK');
    $existent->setAppuntamento(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Appuntamento - VALID TYPE');
    // colloquio
    $property = $this->getPrivateProperty('App\Entity\RichiestaColloquio', 'colloquio');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Colloquio - NOT BLANK');
    $existent->setColloquio($this->getReference("colloquio_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Colloquio - VALID NOT BLANK');
    // alunno
    $property = $this->getPrivateProperty('App\Entity\RichiestaColloquio', 'alunno');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Alunno - NOT BLANK');
    $existent->setAlunno($this->getReference("alunno_1A_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NOT BLANK');
    // genitore
    $property = $this->getPrivateProperty('App\Entity\RichiestaColloquio', 'genitore');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Genitore - NOT BLANK');
    $existent->setGenitore($this->getReference("genitore2_1A_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Genitore - VALID NOT BLANK');
    // genitoreAnnulla
    $existent->setGenitoreAnnulla(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::GenitoreAnnulla - VALID NULL');
    // stato
    $existent->setStato('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Stato - CHOICE');
    $existent->setStato('R');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Stato - VALID CHOICE');
  }

}
