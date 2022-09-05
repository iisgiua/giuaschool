<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\DatabaseTestCase;


/**
* Unit test dell'entità Richiesta
*
*/
class RichiestaTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Richiesta';
    // campi da testare
    $this->fields = ['inviata', 'gestita', 'valori', 'documento', 'allegati', 'stato', 'messaggio', 'utente', 'definizioneRichiesta'];
    $this->noStoredFields = [];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = 'EntityTestFixtures';
    // SQL read
    $this->canRead = ['gs_richiesta' => ['id', 'creato', 'modificato', 'inviata', 'gestita', 'valori', 'documento', 'allegati', 'stato', 'messaggio', 'utente_id', 'definizione_richiesta_id']];
    // SQL write
    $this->canWrite = ['gs_richiesta' => ['id', 'creato', 'modificato', 'inviata', 'gestita', 'valori', 'documento', 'allegati', 'stato', 'messaggio', 'utente_id', 'definizione_richiesta_id']];
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
          ($field == 'inviata' ? $this->faker->dateTime() :
          ($field == 'gestita' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'valori' ? $this->faker->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'documento' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'allegati' ? $this->faker->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'stato' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'messaggio' ? $this->faker->text() :
          ($field == 'utente' ? $this->getReference("utente_1") :
          ($field == 'definizioneRichiesta' ? $this->getReference("definizione_richiesta_1") :
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
      $data[$i]['inviata'] = $this->faker->dateTime();
      $o[$i]->setInviata($data[$i]['inviata']);
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
    $this->assertSame('Richiesta del '.$existent->getInviata()->format('d/m/Y').' da '.$existent->getUtente(), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'inviata' => $existent->getInviata()->format('d/m/y H:i'),
      'gestita' => $existent->getGestita() ? $existent->getGestita()->format('d/m/y H:i') : '',
      'valori' =>  $existent->getValori(),
      'documento' =>  $existent->getDocumento(),
      'allegati' =>  $existent->getAllegati(),
      'stato' =>  $existent->getStato(),
      'messaggio' =>  $existent->getMessaggio(),
      'utente' => $existent->getUtente()->getId(),
      'definizioneRichiesta' => $existent->getDefinizioneRichiesta()->getId()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // inviata
    $property = $this->getPrivateProperty('App\Entity\Richiesta', 'inviata');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Inviata - NOT BLANK');
    $existent->setInviata(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Inviata - VALID NOT BLANK');
    // gestita
    $existent->setGestita(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Gestita - VALID NULL');
    // stato
    $existent->setStato('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Stato - CHOICE');
    $existent->setStato('I');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Stato - VALID CHOICE');
    // utente
    $property = $this->getPrivateProperty('App\Entity\Richiesta', 'utente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Utente - NOT BLANK');
    $existent->setUtente($this->getReference("utente_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Utente - VALID NOT BLANK');
    // definizioneRichiesta
    $property = $this->getPrivateProperty('App\Entity\Richiesta', 'definizioneRichiesta');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::DefinizioneRichiesta - NOT BLANK');
    $existent->setDefinizioneRichiesta($this->getReference("definizione_richiesta_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::DefinizioneRichiesta - VALID NOT BLANK');
  }

}
