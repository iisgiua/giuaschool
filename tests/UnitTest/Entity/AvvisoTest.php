<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Avviso
 *
 * @author Antonello Dessì
 */
class AvvisoTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Avviso';
    // campi da testare
    $this->fields = ['tipo', 'data', 'ora', 'oraFine', 'cattedra', 'materia', 'oggetto', 'testo', 'allegati', 'destinatariAta', 'destinatari', 'filtroTipo', 'filtro', 'docente'];
    $this->noStoredFields = ['annotazioni', 'sedi'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = 'EntityTestFixtures';
    // SQL read
    $this->canRead = ['gs_avviso' => ['id', 'creato', 'modificato', 'tipo', 'data', 'ora', 'ora_fine', 'cattedra_id', 'materia_id', 'oggetto', 'testo', 'allegati', 'destinatari_ata', 'destinatari', 'filtro_tipo', 'filtro', 'docente_id'],
      'gs_annotazione' => '*',
      'gs_classe' => '*',
      'gs_sede' => '*'];
    // SQL write
    $this->canWrite = ['gs_avviso' => ['id', 'creato', 'modificato', 'tipo', 'data', 'ora', 'ora_fine', 'cattedra_id', 'materia_id', 'oggetto', 'testo', 'allegati', 'destinatari_ata', 'destinatari', 'filtro_tipo', 'filtro', 'docente_id']];
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
          ($field == 'ora' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'oraFine' ? $this->faker->optional($weight = 50, $default = null)->dateTime() :
          ($field == 'cattedra' ? $this->getReference("cattedra_1") :
          ($field == 'materia' ? $this->getReference("materia_1") :
          ($field == 'oggetto' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'testo' ? $this->faker->text() :
          ($field == 'allegati' ? $this->faker->optional($weight = 50, $default = array())->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'destinatariAta' ? $this->faker->optional($weight = 50, $default = array())->passthrough($this->faker->sentences($i)) :
          ($field == 'destinatari' ? $this->faker->optional($weight = 50, $default = array())->passthrough($this->faker->sentences($i)) :
          ($field == 'filtroTipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtro' ? $this->faker->optional($weight = 50, $default = array())->passthrough($this->faker->sentences($i)) :
          ($field == 'docente' ? $this->getReference("docente_1") :
          null))))))))))))));
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
    // addSedi
    $items = $existent->getSedi()->toArray();
    $item = new \App\Entity\Sede();
    $existent->addSedi($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::addSedi');
    $existent->addSedi($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::addSedi');
    // removeSedi
    $items = $existent->getSedi()->toArray();
    $item = $items[0];
    $existent->removeSedi($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::removeSedi');
    $existent->removeSedi($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::removeSedi');
    // addAnnotazioni
    $items = $existent->getAnnotazioni()->toArray();
    $item = new \App\Entity\Annotazione();
    $item->setData(new \DateTime());
    $existent->addAnnotazioni($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getAnnotazioni()->toArray()), $this->entity.'::addAnnotazioni');
    $existent->addAnnotazioni($item);
    $this->assertSame(array_values(array_merge($items, [$item])), array_values($existent->getAnnotazioni()->toArray()), $this->entity.'::addAnnotazioni');
    // removeAnnotazioni
    $items = $existent->getAnnotazioni()->toArray();
    $item = $items[0];
    $existent->removeAnnotazioni($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getAnnotazioni()->toArray()), $this->entity.'::removeAnnotazioni');
    $existent->removeAnnotazioni($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getAnnotazioni()->toArray()), $this->entity.'::removeAnnotazioni');
    // addAllegato
    $existent->setAllegati([]);
    $fl1 = new \Symfony\Component\HttpFoundation\File\File(__FILE__);
    $existent->addAllegato($fl1);
    $fl2 = new \Symfony\Component\HttpFoundation\File\File(__DIR__.'/../../data/image2.png');
    $existent->addAllegato($fl2);
    $existent->addAllegato($fl1);
    $this->assertSame([$fl1->getBasename(), $fl2->getBasename()], $existent->getAllegati(), $this->entity.'::addAllegato');
    // removeAllegato
    $existent->removeAllegato($fl1);
    $existent->removeAllegato($fl1);
    $this->assertSame(array_values([$fl2->getBasename()]), array_values($existent->getAllegati()), $this->entity.'::removeAllegato');
    // addDestinatarioAta
    $existent->setDestinatariAta([]);
    $existent->addDestinatarioAta('uno');
    $existent->addDestinatarioAta('due');
    $existent->addDestinatarioAta('uno');
    $this->assertSame(['uno', 'due'], $existent->getDestinatariAta(), $this->entity.'::addDestinatarioAta');
    // removeDestinatarioAta
    $existent->removeDestinatarioAta('uno');
    $existent->removeDestinatarioAta('uno');
    $this->assertSame(array_values(['due']), array_values($existent->getDestinatariAta()), $this->entity.'::removeDestinatarioAta');
    // addDestinatario
    $existent->setDestinatari([]);
    $existent->addDestinatario('uno');
    $existent->addDestinatario('due');
    $existent->addDestinatario('uno');
    $this->assertSame(['uno', 'due'], $existent->getDestinatari(), $this->entity.'::addDestinatario');
    // removeDestinatario
    $existent->removeDestinatario('uno');
    $existent->removeDestinatario('uno');
    $this->assertSame(array_values(['due']), array_values($existent->getDestinatari()), $this->entity.'::removeDestinatario');
    // addFiltro
    $existent->setFiltro([]);
    $existent->addFiltro('uno');
    $existent->addFiltro('due');
    $existent->addFiltro('due');
    $this->assertSame(['uno', 'due'], $existent->getFiltro(), $this->entity.'::addFiltro');
    // removeFiltro
    $existent->removeFiltro('uno');
    $existent->removeFiltro('uno');
    $this->assertSame(array_values(['due']), array_values($existent->getFiltro()), $this->entity.'::removeFiltro');
    // toString
    $this->assertSame('Avviso: '.$existent->getOggetto(), (string) $existent, $this->entity.'::toString');
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
    // sedi
    $property = $this->getPrivateProperty('\App\Entity\Avviso', 'sedi');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Sedi - NOT BLANK');
    $existent->setSedi(new \Doctrine\Common\Collections\ArrayCollection([$this->getReference("sede_1")]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sedi - VALID NOT BLANK');
    // data
    $property = $this->getPrivateProperty('App\Entity\Avviso', 'data');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Data - NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID TYPE');
    // ora
    $existent->setOra(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ora - VALID TYPE');
    $existent->setOra(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Ora - VALID NULL');
    // oraFine
    $existent->setOraFine(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::OraFine - VALID TYPE');
    $existent->setOraFine(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::OraFine - VALID NULL');
    // cattedra
    $existent->setCattedra(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Cattedra - VALID NULL');
    // materia
    $existent->setMateria(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Materia - VALID NULL');
    // oggetto
    $existent->setOggetto(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Oggetto - MAX LENGTH');
    $existent->setOggetto(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Oggetto - VALID MAX LENGTH');
    // filtroTipo
    $existent->setFiltroTipo('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::FiltroTipo - CHOICE');
    $existent->setFiltroTipo('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::FiltroTipo - VALID CHOICE');
    // docente
    $property = $this->getPrivateProperty('App\Entity\Avviso', 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($this->getReference("docente_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
  }

}
