<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Circolare
 *
 * @author Antonello Dessì
 */
class CircolareTest extends EntityTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Circolare';
    // campi da testare
    $this->fields = ['anno', 'numero', 'data', 'oggetto', 'documento', 'allegati', 'ata', 'dsga', 'genitori', 'filtroGenitori', 'alunni', 'filtroAlunni', 'coordinatori', 'filtroCoordinatori', 'docenti', 'filtroDocenti', 'altri', 'firma', 'notifica', 'pubblicata'];
    $this->noStoredFields = ['sedi'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_circolare' => ['id', 'creato', 'modificato', 'anno', 'numero', 'data', 'oggetto', 'documento', 'allegati', 'ata', 'dsga', 'genitori', 'filtro_genitori', 'alunni', 'filtro_alunni', 'coordinatori', 'filtro_coordinatori', 'docenti', 'filtro_docenti', 'altri', 'firma', 'notifica', 'pubblicata'],
      'gs_sede' => '*'];
    // SQL write
    $this->canWrite = ['gs_circolare' => ['id', 'creato', 'modificato', 'anno', 'numero', 'data', 'oggetto', 'documento', 'allegati', 'ata', 'dsga', 'genitori', 'filtro_genitori', 'alunni', 'filtro_alunni', 'coordinatori', 'filtro_coordinatori', 'docenti', 'filtro_docenti', 'altri', 'firma', 'notifica', 'pubblicata']];
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
          ($field == 'numero' ? $this->faker->randomNumber(4, false) :
          ($field == 'data' ? $this->faker->dateTime() :
          ($field == 'oggetto' ? $this->faker->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'documento' ? $this->faker->fileObj() :
          ($field == 'allegati' ? $this->faker->optional($weight = 50, $default = array())->passthrough(array_combine($this->faker->words($i), $this->faker->sentences($i))) :
          ($field == 'ata' ? $this->faker->boolean() :
          ($field == 'dsga' ? $this->faker->boolean() :
          ($field == 'genitori' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroGenitori' ? $this->faker->optional($weight = 50, $default = array())->passthrough($this->faker->sentences($i)) :
          ($field == 'alunni' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroAlunni' ? $this->faker->optional($weight = 50, $default = array())->passthrough($this->faker->sentences($i)) :
          ($field == 'coordinatori' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroCoordinatori' ? $this->faker->optional($weight = 50, $default = array())->passthrough($this->faker->sentences($i)) :
          ($field == 'docenti' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'filtroDocenti' ? $this->faker->optional($weight = 50, $default = array())->passthrough($this->faker->sentences($i)) :
          ($field == 'altri' ? $this->faker->optional($weight = 50, $default = array())->passthrough($this->faker->sentences($i)) :
          ($field == 'firma' ? $this->faker->boolean() :
          ($field == 'notifica' ? $this->faker->boolean() :
          ($field == 'pubblicata' ? $this->faker->boolean() :
          null))))))))))))))))))));
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
      $data[$i]['numero'] = 500 + $i;
      $o[$i]->setNumero($data[$i]['numero']);
      $this->em->flush();
      $this->assertNotSame($data[$i]['modificato'], $o[$i]->getModificato(), $this->entity.'::getModificato - Post-update');
    }
    // controlla gli attributi
    for ($i = 0; $i < 5; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach ($this->fields as $field) {
        if ($field == 'documento') {
          $this->assertSame($data[$i][$field]->getBasename(), $created->{'get'.ucfirst($field)}(),
            $this->entity.'::get'.ucfirst($field));
        } else {
          $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
            $this->entity.'::get'.ucfirst($field));
        }
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
    if (count($items) == 0) {
      $item = new \App\Entity\Sede();
    } else {
      $item = $items[0];
    }
    $existent->removeSedi($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::removeSedi');
    $existent->removeSedi($item);
    $this->assertSame(array_values(array_diff($items, [$item])), array_values($existent->getSedi()->toArray()), $this->entity.'::removeSedi');
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
    // addFiltroGenitori
    $existent->setFiltroGenitori([]);
    $item1 = $this->getReference('genitore1_1A_1');
    $existent->addFiltroGenitori($item1);
    $item2 = $this->getReference('genitore1_1A_2');
    $existent->addFiltroGenitori($item2);
    $existent->addFiltroGenitori($item1);
    $this->assertSame([$item1->getId(), $item2->getId()], array_values($existent->getFiltroGenitori()), $this->entity.'::addFiltroGenitori');
    // removeFiltroGenitori
    $existent->removeFiltroGenitori($item1);
    $existent->removeFiltroGenitori($item1);
    $this->assertSame([$item2->getId()], array_values($existent->getFiltroGenitori()), $this->entity.'::removeFiltroGenitori');
    // addFiltroAlunni
    $existent->setFiltroAlunni([]);
    $item1 = $this->getReference('alunno_1A_1');
    $existent->addFiltroAlunni($item1);
    $item2 = $this->getReference('alunno_1A_2');
    $existent->addFiltroAlunni($item2);
    $existent->addFiltroAlunni($item1);
    $this->assertSame([$item1->getId(), $item2->getId()], array_values($existent->getFiltroAlunni()), $this->entity.'::addFiltroAlunni');
    // removeFiltroAlunni
    $existent->removeFiltroAlunni($item1);
    $existent->removeFiltroAlunni($item1);
    $this->assertSame([$item2->getId()], array_values($existent->getFiltroAlunni()), $this->entity.'::removeFiltroAlunni');
    // addFiltroCoordinatori
    $existent->setFiltroCoordinatori([]);
    $item1 = $this->getReference('docente_curricolare_1');
    $existent->addFiltroCoordinatori($item1);
    $item2 = $this->getReference('docente_curricolare_2');
    $existent->addFiltroCoordinatori($item2);
    $existent->addFiltroCoordinatori($item1);
    $this->assertSame([$item1->getId(), $item2->getId()], array_values($existent->getFiltroCoordinatori()), $this->entity.'::addFiltroCoordinatori');
    // removeFiltroCoordinatori
    $existent->removeFiltroCoordinatori($item1);
    $existent->removeFiltroCoordinatori($item1);
    $this->assertSame([$item2->getId()], array_values($existent->getFiltroCoordinatori()), $this->entity.'::removeFiltroCoordinatori');
    // addFiltroDocenti
    $existent->setFiltroDocenti([]);
    $item1 = $this->getReference('docente_curricolare_1');
    $existent->addFiltroDocenti($item1);
    $item2 = $this->getReference('docente_curricolare_2');
    $existent->addFiltroDocenti($item2);
    $existent->addFiltroDocenti($item1);
    $this->assertSame([$item1->getId(), $item2->getId()], array_values($existent->getFiltroDocenti()), $this->entity.'::addFiltroDocenti');
    // removeFiltroDocenti
    $existent->removeFiltroDocenti($item1);
    $existent->removeFiltroDocenti($item1);
    $this->assertSame([$item2->getId()], array_values($existent->getFiltroDocenti()), $this->entity.'::removeFiltroDocenti');
    // addAltro
    $existent->setAltri([]);
    $existent->addAltro('uno');
    $existent->addAltro('due');
    $existent->addAltro('uno');
    $this->assertSame(['uno', 'due'], $existent->getAltri(), $this->entity.'::addAltro');
    // removeAltro
    $existent->removeAltro('uno');
    $existent->removeAltro('uno');
    $this->assertSame(array_values(['due']), array_values($existent->getAltri()), $this->entity.'::removeAltro');
    // toString
    $this->assertSame('Circolare del '.$existent->getData()->format('d/m/Y').' n. '.$existent->getNumero(), (string) $existent, $this->entity.'::toString');
  }

  /**
   * Test validazione dei dati
   */
  public function testValidation() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->findOneBy([]);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - VALID OBJECT');
    // sedi
    $savedProperty = $existent->getSedi();
    $property = $this->getPrivateProperty('App\Entity\Circolare', 'sedi');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Sedi - NOT BLANK');
    $existent->setSedi($savedProperty);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Sedi - VALID NOT BLANK');
    // data
    $property = $this->getPrivateProperty('App\Entity\Circolare', 'data');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Data - NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID NOT BLANK');
    $existent->setData(new \DateTime());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Data - VALID TYPE');
    // oggetto
    $property = $this->getPrivateProperty('App\Entity\Circolare', 'oggetto');
    $property->setValue($existent, '');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Oggetto - NOT BLANK');
    $existent->setOggetto($this->faker->randomLetter());
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Oggetto - VALID NOT BLANK');
    $existent->setOggetto(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Oggetto - MAX LENGTH');
    $existent->setOggetto(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Oggetto - VALID MAX LENGTH');
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
    // coordinatori
    $existent->setCoordinatori('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Coordinatori - CHOICE');
    $existent->setCoordinatori('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Coordinatori - VALID CHOICE');
    // docenti
    $existent->setDocenti('*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::Docenti - CHOICE');
    $existent->setDocenti('N');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docenti - VALID CHOICE');
    // legge dati esistenti
    $this->em->flush();
    $objects = $this->em->getRepository($this->entity)->findBy([]);
    // unique anno-numero
    $annoSaved = $objects[1]->getAnno();
    $objects[1]->setAnno($objects[0]->getAnno());
    $numeroSaved = $objects[1]->getNumero();
    $objects[1]->setNumero($objects[0]->getNumero());
    $err = $this->val->validate($objects[1]);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.unique', $this->entity.'::anno-numero - UNIQUE');
    $objects[1]->setAnno($annoSaved);
    $objects[1]->setNumero($numeroSaved);
    // unique
    $newObject = new \App\Entity\Circolare();
    foreach ($this->fields as $field) {
      if ($field == 'documento') {
        $newObject->setDocumento(new \Symfony\Component\HttpFoundation\File\File(dirname(dirname(__DIR__)).'/data/'.$objects[0]->getDocumento()));
      } else {
        $newObject->{'set'.ucfirst($field)}($objects[0]->{'get'.ucfirst($field)}());
      }
    }
    $err = $this->val->validate($newObject);
    $msgs = [];
    foreach ($err as $e) {
      $msgs[] = $e->getMessageTemplate();
    }
    $this->assertEquals(array_fill(0, 1, 'field.unique'), $msgs, $this->entity.' - UNIQUE');
  }

}
