<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\Alunno;
use App\Entity\DefinizioneScrutinio;
use App\Entity\Docente;
use App\Entity\Provisioning;
use App\Entity\StoricoEsito;
use App\Entity\StoricoVoto;
use App\Entity\Scrutinio;
use App\Form\ConfigurazioneType;
use App\Form\ModuloType;
use App\Form\UtenteType;
use App\Kernel;
use App\Util\ArchiviazioneUtil;
use App\Util\LogHandler;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * SistemaController - gestione parametri di sistema e funzioni di utlità
 *
 * @author Antonello Dessì
 */
class TEMP extends BaseController {

  /**
   * Esegue l'aggiornamento a una nuova versione
   *
   * @param Request $request Pagina richiesta
   * @param int $step Passo della procedura
   *
   * @return Response Pagina di risposta
   *
   * @Route("/TEMP/{step}", name="temp",
   *    requirements={"step": "\d+"},
   *    defaults={"step": "0"},
   *    methods={"GET"})
   *
   * @IsGranted("ROLE_AMMINISTRATORE")
   */
  public function aggiornaAction(Request $request, int $step): Response {
    // inizializza
    $dati = [];
    $info = [];
    // assicura che lo script non sia interrotto
    ini_set('max_execution_time', 0);
    $info['step'] = 0;
    $info['prossimo'] = 0;
    // esegue passi
    switch($step) {
      case 0:   // controlli iniziali
        $url = 'https://github.com/iisgiua/giuaschool-docs/raw/master/_data/version.yml';
        $pagina = file_get_contents($url);
        preg_match('/^tag:\s*([0-9\.]+)$/m', $pagina, $trovati);
        if (count($trovati) != 2) {
          // errore recupero versione
          $info['tipo'] = 'danger';
          $info['messaggio'] = 'exception.aggiornamento_no_versione';
          break;
        }
        // controlla versione
        $nuovaVersione = $trovati[1];
        $versione = $this->em->getRepository('App\Entity\Configurazione')->getParametro('versione');
        if (version_compare($nuovaVersione, $versione, '<=')) {
          // sistema già aggiornato
          $info['tipo'] = 'info';
          $info['messaggio'] = 'message.sistema_aggiornato';
          break;
        }
        // nuova versione presente
        $dati['versione'] = $nuovaVersione;
        if (!extension_loaded('zip')) {
          // zip non supportato
          $info['tipo'] = 'danger';
          $info['messaggio'] = 'exception.aggiornamento_zip_non_presente';
          break;
        }
        // controlla esistenza file
        $file = dirname(__DIR__).'/Install/v'.$nuovaVersione.'.ok';
        if (file_exists($file)) {
          // file già scaricato: salta il passo successivo
          $info['tipo'] = 'success';
          $info['messaggio'] = 'message.aggiornamento_scaricato';
          $info['prossimo'] = 2;
        } else {
          // file da scaricare
          $info['tipo'] = 'success';
          $info['messaggio'] = 'message.aggiornamento_possibile';
          $info['prossimo'] = 1;
        }
        $this->reqstack->getSession()->set('/APP/ROUTE/sistema_aggiorna/versione', $nuovaVersione);
        break;
      case 1:   // scarica file
        $nuovaVersione = $this->reqstack->getSession()->get('/APP/ROUTE/sistema_aggiorna/versione');
        $dati['versione'] = $nuovaVersione;
        $url = 'https://github.com/iisgiua/giuaschool/releases/download/v'.$nuovaVersione.
          '/giuaschool-release-v'.$nuovaVersione.'.zip';
        $file = dirname(__DIR__).'/Install/v'.$nuovaVersione.'.zip';
        // scarica file
        $bytes = file_put_contents($file, file_get_contents($url));
        if ($bytes == 0) {
          $info['tipo'] = 'danger';
          $info['messaggio'] = 'exception.aggiornamento_errore_file';
          break;
        }
        // conferma scaricamento
        $file = dirname(__DIR__).'/Install/v'.$nuovaVersione.'.ok';
        file_put_contents($file, '');
        $info['tipo'] = 'success';
        $info['messaggio'] = 'message.aggiornamento_scaricato';
        $info['prossimo'] = 2;
        break;
      case 2:   // installazione
        // salva dati per l'installazione
        $nuovaVersione = $this->reqstack->getSession()->get('/APP/ROUTE/sistema_aggiorna/versione');
        $token = bin2hex(random_bytes(16));
        $contenuto = 'token="'.$token.'"'."\n".
          'version="'.$nuovaVersione.'"'."\n";
        file_put_contents(dirname(dirname(__DIR__)).'/.gs-updating', $contenuto);
        // reindirizza a pagina di installazione
        $urlPath = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http').
          '://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $urlPath = substr($urlPath, 0, - strlen('/TEMP/2'));
        return $this->redirect($urlPath."/install/update.php?token=$token&step=1");
        break;
    }
    // mostra la pagina di risposta
    return $this->render('TEMP.html.twig', array(
      'info' => $info,
      'dati' => $dati,
      'pagina_titolo' => 'page.sistema.aggiorna',
      'titolo' => 'title.sistema.aggiorna'
    ));
  }

}
