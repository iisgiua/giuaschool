<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Staff;
use App\Entity\Alunno;
use App\Entity\Preside;
use App\Entity\Assenza;
use App\Entity\Avviso;
use App\Entity\StoricoEsito;
use App\Util\BachecaUtil;


/**
 * FileController - gestione file upload
 *
 * @author Antonello Dessì
 */
class FileController extends AbstractController {

  /**
   * Esegue l'upload di un file tramite chiamata AJAX.
   *
   * @param Request $request Pagina richiesta
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param string $pagina Nome della pagina di invio del form
   * @param string $param Nome del parametro usato nel form
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/file/upload/{pagina}/{param}", name="file_upload",
   *    requirements={"pagina": "\w+", "param": "\w+"},
   *    methods={"POST"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function uploadAction(Request $request, RequestStack $reqstack, $pagina, $param) {
    $risposta = array();
    // legge file
    $files = $request->files->get($param);
    // imposta directory temporanea
    $dir = $this->getParameter('dir_tmp');
    // controlla upload
    foreach ($files as $k=>$file) {
      $nomefile = md5(uniqid()).'-'.rand(1,1000).'.'.$file->getClientOriginalExtension();
      if ($file->isValid() && $file->move($dir, $nomefile)) {
        // file caricato senza errori
        $risposta[$k]['type'] = 'uploaded';
        $risposta[$k]['temp'] = $nomefile;
        $risposta[$k]['name'] = $file->getClientOriginalName();
        $risposta[$k]['ext'] = $file->getClientOriginalExtension();
        $fl = new File($dir.'/'.$nomefile);
        $risposta[$k]['size'] = $fl->getSize();
      } else {
        // errore
        $res = new Response('Errore nel caricamento del file', 500);
        return $res;
      }
    }
    // memorizza in sessione
    $var_sessione = '/APP/FILE/'.$pagina.'/'.$param;
    $reqstack->getSession()->set($var_sessione, array_merge($risposta, $reqstack->getSession()->get($var_sessione, [])));
    // restituisce risposta
    return new JsonResponse($risposta);
  }

  /**
   * Rimuove il file caricato tramite chiamata AJAX.
   *
   * @param Request $request Pagina richiesta
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param string $pagina Nome della pagina di invio del form
   * @param string $param Nome del parametro usato nel form
   *
   * @return JsonResponse Informazioni di risposta
   *
   * @Route("/file/remove/{pagina}/{param}", name="file_remove",
   *    requirements={"pagina": "\w+", "param": "\w+"},
   *    methods={"POST"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function removeAction(Request $request, RequestStack $reqstack, $pagina, $param) {
    // legge file
    $file = $request->request->get($param);
    // imposta directory temporanea
    $dir = $this->getParameter('dir_tmp');
    // rimuove file
    if ($file) {
      $fs = new Filesystem();
      $var_sessione = '/APP/FILE/'.$pagina.'/'.$param;
      $vs = $reqstack->getSession()->get($var_sessione, []);
      foreach ($vs as $k=>$f) {
        if ($f['type'] == 'uploaded' && $f['temp'] == $file['temp']) {
          // trovato: cancella
          $fs->remove($dir.'/'.$f['temp']);
          unset($vs[$k]);
          break;
        } elseif ($f['type'] == 'existent' && $f['name'] == $file['name']) {
          // segna per cancellarlo in seguito
          $f['type'] = 'removed';
          $vs[$k] = $f;
          break;
        }
      }
      // memorizza sessione
      $reqstack->getSession()->set($var_sessione, $vs);
    }
    // restituisce risposta vuota
    return new JsonResponse([]);
  }

  /**
   * Esegue il download di un allegato di un avviso.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $avviso ID dell'avviso
   * @param int $allegato Numero dell'allegato
   *
   * @return Response Documento inviato in risposta
   *
   * @Route("/file/avviso/{avviso}/{allegato}", name="file_avviso",
   *    requirements={"avviso": "\d+", "allegato": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_UTENTE")
   */
  public function avvisoAction(EntityManagerInterface $em, BachecaUtil $bac,
                                $avviso, $allegato) {
    // controllo avviso
    $avviso = $em->getRepository('App\Entity\Avviso')->find($avviso);
    if (!$avviso) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo allegato
    if ($allegato < 1 || $allegato > count($avviso->getAllegati())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo permessi
    if (!$bac->permessoLettura($avviso, $this->getUser())) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // file
    $file = new File($this->getParameter('dir_avvisi').'/'.
      array_values($avviso->getAllegati())[$allegato - 1]);
    // nome da visualizzare
    $nome = 'avviso-'.$avviso->getId().'-allegato-'.$allegato.'.'.$file->guessExtension();
    // invia il documento
    return $this->file($file, $nome, ResponseHeaderBag::DISPOSITION_INLINE);
  }

  /**
   * Esegue il download dei documenti dello scrutinio per la segreteria.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param string $tipo Tipo del documento da scaricare
   * @param int $id ID dell'alunno a cui si fa riferimento
   *
   * @return Response Documento inviato in risposta
   *
   * @Route("/file/download/segreteria/{tipo}/{id}", name="file_download_segreteria",
   *    requirements={"tipo": "V|VS|VX|R|RS|RX|C|CS|CX", "id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_ATA")
   */
  public function downloadSegreteriaAction(EntityManagerInterface $em, $tipo, $id) {
    // controllo
    $storico = $em->getRepository('App\Entity\StoricoEsito')->findOneByAlunno($id);
    if (!$storico) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controllo accesso alla funzione
    if (!$this->getUser()->getSegreteria()) {
      // ATA non abiliatato alla segreteria
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // file da scaricare
    $percorso = $this->getParameter('kernel.project_dir').'/FILES/archivio/scrutini/storico/'.
      $storico->getClasse().'/';
    switch ($tipo) {
      case 'V':
        // verbale scrutinio finale
        $file = new File($percorso.$storico->getClasse().'-scrutinio-finale-verbale.pdf');
        break;
      case 'VS':
        // verbale esame sospesi
        $file = new File($percorso.$storico->getClasse().'-scrutinio-sospesi-verbale.pdf');
        break;
      case 'VX':
        // verbale scrutinio rinviato
        $file = new File($percorso.$storico->getClasse().'-scrutinio-rinviato-verbale.pdf');
        break;
      case 'R':
        // riepilogo voti scrutinio finale
        $file = new File($percorso.$storico->getClasse().'-scrutinio-finale-riepilogo-voti.pdf');
        break;
      case 'RS':
        // riepilogo voti scrutinio esame sospesi
        $file = new File($percorso.$storico->getClasse().'-scrutinio-sospesi-riepilogo-voti.pdf');
        break;
      case 'RX':
        // riepilogo voti scrutinio rinviato
        $file = new File($percorso.$storico->getClasse().'-scrutinio-rinviato-riepilogo-voti.pdf');
        break;
      case 'C':
        // certificazioni scrutinio finale
        $file = new File($percorso.$storico->getClasse().'-scrutinio-finale-certificazioni.pdf');
        break;
      case 'CS':
        // certificazioni scrutinio esame sospesi
        $file = new File($percorso.$storico->getClasse().'-scrutinio-sospesi-certificazioni.pdf');
        break;
      case 'CX':
        // certificazioni scrutinio rinviato
        $file = new File($percorso.$storico->getClasse().'-scrutinio-rinviato-certificazioni.pdf');
        break;
    }
    // invia il documento
    return $this->file($file);
  }

  /**
   * Esegue il download del certificato del tipo indicato.
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param string $tipo Tipo del certificato da scaricare [D=autodichiarazione]
   * @param int $id ID dell'oggetto di riferimento
   *
   * @return Response Certificato inviato in risposta
   *
   * @Route("/file/certificato/{tipo}/{id}", name="file_certificato",
   *    requirements={"tipo": "D", "id": "\d+"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_DOCENTE")
   */
  public function certificatoAction(EntityManagerInterface $em, $tipo, $id) {
    // init
    $fs = new Filesystem();
    if ($tipo == 'D') {
      $assenza = $em->getRepository('App\Entity\Assenza')->find($id);
      if (!$assenza) {
        // errore assenza non definita
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $percorso = $this->getParameter('dir_classi').'/'.
        $assenza->getAlunno()->getClasse()->getAnno().$assenza->getAlunno()->getClasse()->getSezione().'/certificati/';
      $nomefile = 'AUTODICHIARAZIONE-'.$assenza->getAlunno()->getId().'-'.$id.'.pdf';
    }
    // controllo esistenza certificato
    if (!$fs->exists($percorso.$nomefile)) {
      // errore certificato non esiste
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // file
    $file = new File($percorso.$nomefile);
    // invia il certificato
    return $this->file($file);
  }

}
