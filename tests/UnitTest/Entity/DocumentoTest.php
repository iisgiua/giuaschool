<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Entity;

use App\Entity\Documento;
use ReflectionClass;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Materia;
use App\Tests\EntityTestCase;


/**
 * Unit test dell'entità Documento
 *
 * @author Antonello Dessì
 */
class DocumentoTest extends EntityTestCase {

 /**
   * Definisce dati per i test.
   *
   */
  protected function setUp(): void {
    // nome dell'entità
    $this->entity = Documento::class;
    // campi da testare
    $this->fields = ['tipo', 'docente', 'listaDestinatari', 'materia', 'classe', 'alunno', 'cifrato', 'firma'];
    $this->noStoredFields = ['allegati'];
    $this->generatedFields = ['id', 'creato', 'modificato'];
    // fixture da caricare
    $this->fixtures = '_entityTestFixtures';
    // SQL read
    $this->canRead = ['gs_documento' => ['id', 'creato', 'modificato', 'tipo', 'docente_id', 'lista_destinatari_id', 'materia_id', 'classe_id', 'alunno_id', 'cifrato', 'firma'],
      'gs_sede' => '*',
      'gs_file' => '*',
      'gs_lista_destinatari' => '*'];
    // SQL write
    $this->canWrite = ['gs_documento' => ['id', 'creato', 'modificato', 'tipo', 'docente_id', 'lista_destinatari_id', 'materia_id', 'classe_id', 'alunno_id', 'cifrato', 'firma']];
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
          ($field == 'tipo' ? $this->faker->passthrough(substr($this->faker->text(), 0, 1)) :
          ($field == 'docente' ? $this->getReference("docente_curricolare_1") :
          ($field == 'listaDestinatari' ? $this->getReference("lista_destinatari_SOSTEGNO_".($i + 1)) :
          ($field == 'materia' ? $this->getReference("materia_curricolare_1") :
          ($field == 'classe' ? $this->getReference("classe_1A") :
          ($field == 'alunno' ? $this->getReference("alunno_1A_1") :
          ($field == 'cifrato' ? $this->faker->optional($weight = 50, $default = '')->passthrough(substr($this->faker->text(), 0, 255)) :
          ($field == 'firma' ? $this->faker->boolean() :
          null))))))));
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
      $data[$i]['cifrato'] = substr($this->faker->text(), 0, 255);
      $o[$i]->setCifrato($data[$i]['cifrato']);
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
    // addAllegato
    $existent->setAllegati(new ArrayCollection());
    $existent->addAllegato($this->getReference('file_word_1'));
    $existent->addAllegato($this->getReference('file_word_2'));
    $existent->addAllegato($this->getReference('file_word_1'));
    $this->assertSame([$this->getReference('file_word_1'), $this->getReference('file_word_2')], $existent->getAllegati()->toArray(), $this->entity.'::addAllegato');
    // removeAllegato
    $existent->removeAllegato($this->getReference('file_word_1'));
    $existent->removeAllegato($this->getReference('file_word_1'));
    $this->assertSame(array_values([$this->getReference('file_word_2')]), array_values($existent->getAllegati()->toArray()), $this->entity.'::removeAllegato');
    // toString
    $this->assertSame('Documento #'.$existent->getId(), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'tipo' => $existent->getTipo(),
      'docente' => $existent->getDocente()->getId(),
      'listaDestinatari' => $existent->getListaDestinatari()->datiVersione(),
      'allegati' => array_map(fn($ogg) => $ogg->datiVersione(), $existent->getAllegati()->toArray()),
      'materia' => $existent->getMateria() ? $existent->getMateria()->getId() : null,
      'classe' => $existent->getClasse() ? $existent->getClasse()->getId() : null,
      'alunno' => $existent->getAlunno() ? $existent->getAlunno()->getId() : null,
      'cifrato' => $existent->getCifrato(),
      'firma' => $existent->getFirma()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $dt['tipo'] = 'P';
    $existent->setTipo('P');
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $materia = $this->em->getRepository(Materia::class)->find(1);
    $existent->setMateria($materia);
    $dt['materia'] = $materia->getId();
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $existent->getListaDestinatari()->setfiltroGenitori([10, 20]);
    $dt['listaDestinatari']['filtroGenitori'] = [10, 20];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
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
    $existent->setTipo('L');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Tipo - VALID CHOICE');
    // docente
    $property = $this->getPrivateProperty(Documento::class, 'docente');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Docente - NOT BLANK');
    $existent->setDocente($this->getReference("docente_curricolare_1"));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Docente - VALID NOT BLANK');
    // listaDestinatari
    $temp = $existent->getListaDestinatari();
    $property = $this->getPrivateProperty(Documento::class, 'listaDestinatari');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::ListaDestinatari - NOT BLANK');
    $existent->setListaDestinatari($temp);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::ListaDestinatari - VALID NOT BLANK');
    // allegati
    $property = $this->getPrivateProperty(Documento::class, 'allegati');
    $property->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::Allegati - NOT BLANK');
    $existent->setAllegati(new ArrayCollection([$this->getReference("file_1")]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Allegati - VALID NOT BLANK');
    // materia
    $existent->setMateria(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Materia - VALID NULL');
    // classe
    $existent->setClasse(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Classe - VALID NULL');
    // alunno
    $existent->setAlunno(null);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Alunno - VALID NULL');
    // cifrato
    $existent->setCifrato(str_repeat('*', 256));
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::Cifrato - MAX LENGTH');
    $existent->setCifrato(str_repeat('*', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::Cifrato - VALID MAX LENGTH');
  }

}
