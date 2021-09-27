<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Tests\UnitTest\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use App\Tests\DatabaseTestCase;
use App\DataFixtures\DocumentoFixtures;
use App\DataFixtures\SedeFixtures;
use App\DataFixtures\DocenteFixtures;
use App\DataFixtures\StaffFixtures;
use App\DataFixtures\AtaFixtures;
use App\DataFixtures\AlunnoFixtures;


/**
 * Unit test della classe
 */
class DocumentoTest extends DatabaseTestCase {

  /**
   * Costruttore
   * Definisce dati per i test.
   *
   */
  public function __construct() {
    parent::__construct();
    // nome dell'entità
    $this->entity = '\App\Entity\Documento';
    // campi da testare
    $this->fields = ['tipo', 'docente', 'listaDestinatari', 'allegati', 'materia', 'classe', 'alunno',
      'cifrato', 'firma'];
    // fixture da caricare
    $this->fixtures = ['g:Test'];
    // SQL read
    $this->canRead = [
      'gs_documento' => ['id', 'creato', 'modificato', 'tipo', 'docente_id', 'lista_destinatari_id',
        'allegati', 'materia_id', 'classe_id', 'alunno_id', 'cifrato', 'firma'],
      'gs_file' => '*',
      'gs_utente' => '*',
      'gs_lista_destinatari' => '*',
      'gs_materia' => '*',
      'gs_classe' => '*',
      'gs_sede' => '*'];
    // SQL writedd
    $this->canWrite = [
      'gs_documento' => ['id', 'creato', 'modificato', 'tipo', 'docente_id', 'lista_destinatari_id',
        'allegati', 'materia_id', 'classe_id', 'alunno_id', 'cifrato', 'firma'],
      'gs_documento_file' => '*'];
    // SQL exec
    $this->canExecute = ['START TRANSACTION', 'COMMIT'];
  }

  /**
   * Test getter/setter degli attributi, con memorizzazione su database.
   * Sono esclusi gli attributi ereditati.
   */
  public function testAttributi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertEquals(1, $existent->getId(), 'Oggetto esistente');
    // crea nuovi oggetti
    $docenti = $this->em->getRepository('App:Docente')->findBy([]);
    $destinatari = $this->em->getRepository('App:ListaDestinatari')->createQueryBuilder('ld')
      ->where('ld.docenti=:nessuno AND ld.genitori=:nessuno')
      ->setParameters(['nessuno' => 'N'])
      ->getQuery()
      ->getResult();
    $materie = $this->em->getRepository('App:Materia')->findBy([]);
    $classi = $this->em->getRepository('App:Classe')->findBy([]);
    $alunni = $this->em->getRepository('App:Alunno')->findBy([]);
    $file = $this->em->getRepository('App:File')->findBy(['estensione' => 'docx']);
    for ($i = 0; $i < 3; $i++) {
      $o[$i] = new $this->entity();
      foreach ($this->fields as $field) {
        $data[$i][$field] =
          $field == 'tipo' ? $this->faker->randomElement(['L', 'P', 'R', 'M', 'H', 'D', 'C', 'G']) :
          ($field == 'docente' ? $this->faker->randomElement($docenti) :
          ($field == 'listaDestinatari' ? $this->faker->unique()->randomElement($destinatari) :
          ($field == 'allegati' ? new ArrayCollection([$file[$i]]) :
          ($field == 'materia' ? $this->faker->optional(0.6)->randomElement($materie) :
          ($field == 'classe' ? $this->faker->optional(0.6)->randomElement($classi) :
          ($field == 'alunno' ? $this->faker->optional(0.6)->randomElement($alunni) :
          ($field == 'cifrato' ? $this->faker->password(8, 8) :
          $this->faker->boolean())))))));
        $o[$i]->{'set'.ucfirst($field)}($data[$i][$field]);
      }
      $this->assertEmpty($o[$i]->getId(), $this->entity.'::getId Pre-inserimento');
      $this->assertEmpty($o[$i]->getCreato(), $this->entity.'::getCreato Pre-inserimento');
      $this->assertEmpty($o[$i]->getModificato(), $this->entity.'::getModificato Pre-inserimento');
      // memorizza su db
      $this->em->persist($o[$i]);
      $this->em->flush();
      $this->assertNotEmpty($o[$i]->getId(), $this->entity.'::getId Post-inserimento');
      $this->assertNotEmpty($o[$i]->getCreato(), $this->entity.'::getCreato Post-inserimento');
      $this->assertNotEmpty($o[$i]->getModificato(), $this->entity.'::getModificato Post-inserimento');
      $data[$i]['id'] = $o[$i]->getId();
      $data[$i]['creato'] = $o[$i]->getCreato();
      // controlla creato < modificato
      sleep(1);
      $o[$i]->{'set'.ucfirst($this->fields[0])}(!$data[$i][$this->fields[0]]);
      $this->em->flush();
      $o[$i]->{'set'.ucfirst($this->fields[0])}($data[$i][$this->fields[0]]);
      $this->em->flush();
      $this->assertTrue($o[$i]->getCreato() < $o[$i]->getModificato(), $this->entity.'::getCreato < getModificato');
      $data[$i]['modificato'] = $o[$i]->getModificato();
    }
    // controlla gli attributi
    for ($i = 0; $i < 3; $i++) {
      $created = $this->em->getRepository($this->entity)->find($data[$i]['id']);
      foreach (array_merge(['id', 'creato', 'modificato'], $this->fields) as $field) {
        if ($field=='allegati') {
          $this->assertSame($data[$i][$field]->toArray(), $created->{'get'.ucfirst($field)}()->toArray(),
            $this->entity.'::get'.ucfirst($field));
        } else {
          $this->assertSame($data[$i][$field], $created->{'get'.ucfirst($field)}(),
            $this->entity.'::get'.ucfirst($field));
        }
        if ($field == 'allegati') {
          $created->setAllegati(new ArrayCollection());
          $created->addAllegato($file[0]);
          $created->addAllegato($file[1]);
          $created->addAllegato($file[0]);
          $this->assertSame([$file[0], $file[1]], $created->getAllegati()->toArray(), $this->entity.'::addAllegato');
          $created->removeAllegato($file[1]);
          $created->removeAllegato($file[1]);
          $this->assertSame([$file[0]], $created->getAllegati()->toArray(), $this->entity.'::removeAllegato');
        }
      }
    }
    // controlla metodi setId, setCreato e setModificato
    $rc = new \ReflectionClass($this->entity);
    $this->assertFalse($rc->hasMethod('setId'), 'Esiste metodo '.$this->entity.'::setId');
    $this->assertFalse($rc->hasMethod('setCreato'), 'Esiste metodo '.$this->entity.'::setCreato');
    $this->assertFalse($rc->hasMethod('setModificato'), 'Esiste metodo '.$this->entity.'::setModificato');
  }

  /**
   * Test altri metodi
   */
  public function testMetodi() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    // toString
    $this->assertSame('Documento #'.$existent->getId(), (string) $existent, $this->entity.'::toString');
    // datiVersione
    $dt = [
      'tipo' => $existent->getTipo(),
      'docente' => $existent->getDocente()->getId(),
      'listaDestinatari' => $existent->getListaDestinatari()->datiVersione(),
      'allegati' => array_map(function($ogg) { return $ogg->datiVersione(); }, $existent->getAllegati()->toArray()),
      'materia' => $existent->getMateria() ? $existent->getMateria()->getId() : null,
      'classe' => $existent->getClasse() ? $existent->getClasse()->getId() : null,
      'alunno' => $existent->getAlunno() ? $existent->getAlunno()->getId() : null,
      'cifrato' => $existent->getCifrato(),
      'firma' => $existent->getFirma()];
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $dt['tipo'] = 'P';
    $existent->setTipo('P');
    $this->assertSame($dt, $existent->datiVersione(), $this->entity.'::datiVersione');
    $materia = $this->em->getRepository('App:Materia')->find(1);
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
  public function testValidazione() {
    // carica oggetto esistente
    $existent = $this->em->getRepository($this->entity)->find(1);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.' - Oggetto valido');
    // tipo
    $existent->setTipo(null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::tipo - NOT BLANK');
    $existent->setTipo('A');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('p');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.choice', $this->entity.'::tipo - CHOICE');
    $existent->setTipo('P');
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::tipo - VALID CHOICE');
    // docente
    $obj_docente = $this->getPrivateProperty($this->entity, 'docente');
    $obj_docente->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::docente - NOT BLANK');
    $existent->setDocente($this->em->getRepository('App:Docente')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::docente - VALID');
    // listaDestinatari
    $obj_destinatari = $this->getPrivateProperty($this->entity, 'listaDestinatari');
    $obj_destinatari->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::listaDestinatari - NOT BLANK');
    $existent->setListaDestinatari($this->em->getRepository('App:ListaDestinatari')->findOneBy([]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::listaDestinatari - VALID');
    // allegati
    $obj_allegati = $this->getPrivateProperty($this->entity, 'allegati');
    $obj_allegati->setValue($existent, null);
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.notblank', $this->entity.'::allegati - NOT BLANK');
    $existent->setAllegati(new ArrayCollection([$this->em->getRepository('App:File')->find(5)]));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::allegati - VALID');
    // cifrato
    $existent->setCifrato(null);
    $err = $this->val->validate($existent);
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::cifrato - VALID NULL');
    $existent->setCifrato(str_repeat('X', 255).'*');
    $err = $this->val->validate($existent);
    $this->assertTrue(count($err) == 1 && $err[0]->getMessageTemplate() == 'field.maxlength', $this->entity.'::cifrato - MAX LENGTH');
    $existent->setCifrato(str_repeat('X', 255));
    $this->assertCount(0, $this->val->validate($existent), $this->entity.'::cifrato - VALID MAX LENGTH');
  }

}
