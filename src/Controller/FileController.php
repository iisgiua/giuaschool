<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\StoricoEsito;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


/**
 * FileController - gestione file upload
 *
 * @author Antonello Dessì
 */
class FileController extends BaseController {

  /**
   * Esegue l'upload di un file tramite chiamata AJAX.
   *
   * @param Request $request Pagina richiesta
   * @param string $pagina Nome della pagina di invio del form
   * @param string $param Nome del parametro usato nel form
   *
   * @return JsonResponse Informazioni di risposta
   *
   */
  #[Route(path: '/file/upload/{pagina}/{param}', name: 'file_upload', requirements: ['pagina' => '\w+', 'param' => '\w+'], methods: ['POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function upload(Request $request, string $pagina, string $param): Response {
    $risposta = [];
    // legge file
    $files = $request->files->get($param);
    // imposta directory temporanea
    $dir = $this->getParameter('dir_tmp');
    // controlla upload
    foreach ($files as $k=>$file) {
      $nomefile = date('Ymd_His').'_'.bin2hex(random_bytes(8));
      $info = pathinfo($file->getClientOriginalName());
      $nomeCaricato = $info['filename'];
      $tipoCaricato = $file->getClientOriginalExtension();
      if ($file->isValid() && $file->move($dir, $nomefile.'.'.$tipoCaricato)) {
        // file caricato senza errori
        $risposta[$k]['type'] = 'uploaded';
        $risposta[$k]['temp'] = $nomefile;
        $risposta[$k]['name'] = $nomeCaricato;
        $risposta[$k]['ext'] = $tipoCaricato;
        $fl = new File($dir.'/'.$nomefile.'.'.$tipoCaricato);
        $risposta[$k]['size'] = $fl->getSize();
      } else {
        // errore
        $res = new Response('Errore nel caricamento del file', Response::HTTP_INTERNAL_SERVER_ERROR);
        return $res;
      }
    }
    // memorizza in sessione
    $var_sessione = '/APP/FILE/'.$pagina.'/'.$param;
    $this->reqstack->getSession()->set($var_sessione, array_merge($this->reqstack->getSession()->get($var_sessione, []), $risposta));
    // restituisce risposta
    return new JsonResponse($risposta);
  }

  /**
   * Rimuove il file caricato tramite chiamata AJAX.
   *
   * @param Request $request Pagina richiesta
   * @param string $pagina Nome della pagina di invio del form
   * @param string $param Nome del parametro usato nel form
   *
   * @return JsonResponse Informazioni di risposta
   *
   */
  #[Route(path: '/file/remove/{pagina}/{param}', name: 'file_remove', requirements: ['pagina' => '\w+', 'param' => '\w+'], methods: ['POST'])]
  #[IsGranted('ROLE_UTENTE')]
  public function remove(Request $request, string $pagina, string $param): Response {
    // legge file
    $file = $request->request->all($param);
    // imposta directory temporanea
    $dir = $this->getParameter('dir_tmp');
    // rimuove file
    if ($file) {
      $fs = new Filesystem();
      $var_sessione = '/APP/FILE/'.$pagina.'/'.$param;
      $vs = $this->reqstack->getSession()->get($var_sessione, []);
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
      $this->reqstack->getSession()->set($var_sessione, $vs);
    }
    // restituisce risposta vuota
    return new JsonResponse([]);
  }

  /**
   * Esegue il download dei documenti dello scrutinio per la segreteria.
   *
   * @param string $tipo Tipo del documento da scaricare
   * @param int $id ID dell'alunno a cui si fa riferimento
   *
   * @return Response Documento inviato in risposta
   *
   */
  #[Route(path: '/file/download/segreteria/{tipo}/{id}', name: 'file_download_segreteria', requirements: ['tipo' => 'V|VS|VX|R|RS|RX|C|CS|CX', 'id' => '\d+'], methods: ['GET'])]
  #[IsGranted('ROLE_ATA')]
  public function downloadSegreteria(string $tipo, int $id): Response {
    // controllo
    $storico = $this->em->getRepository(StoricoEsito::class)->findOneByAlunno($id);
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

}
