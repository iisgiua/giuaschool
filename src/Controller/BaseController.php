<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Controller;

use App\Entity\MenuOpzione;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


/**
 * BaseController - funzioni di utilità per i controller
 *
 * @author Antonello Dessì
 */
class BaseController extends AbstractController {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  public function __construct(
    protected EntityManagerInterface $em,
    protected RequestStack $reqstack)
  {
  }

  /**
   * Visualizza una pagina HTML, creando la breadcrumb.
   *
   * @param string $categoria Categoria a cui appartiene la pagina
   * @param string $azione Azione svolta dalla pagina
   * @param array $dati Lista di dati tabellari da passare alla vista
   * @param array $info Lista di informazioni singole da passare alla vista
   * @param array $form Oggetto form e messaggi da passare alla vista
   *
   * @return Response Pagina di risposta
   */
  protected function renderHtml(string $categoria, string $azione, array $dati=[],
                                array $info=[], array $form=[]): Response {
    [$azionePrincipale] = explode('_', $azione);
    $tema = $this->reqstack->getSession()->get('/APP/APP/tema', '');
    $breadcrumb = null;
    // legge breadcrumb (solo se nuovo tema)
    if ($tema) {
      $breadcrumb = $this->em->getRepository(MenuOpzione::class)->breadcrumb($categoria.'_'.$azionePrincipale,
        $this->getUser());
    }
    // imposta template
    $template = ($tema ? $tema.'/' : '').$categoria.'/'.$azione.'.html.twig';
    // restituisce vista
    return parent::render($template, [
      'pagina_titolo' => 'page.'.$categoria.'.'.$azionePrincipale,
      'titolo' => 'title.'.$categoria.'.'.$azione,
      'breadcrumb' => $breadcrumb,
      'dati' => $dati,
      'info' => $info,
      'form' => $form]);
  }

  /**
   * Genera un file CSV come risposta, con codifica UTF-8 e BOM.
   *
   * Il BOM (Byte Order Mark) iniziale e' necessario perche i fogli di calcolo su
   * Windows, se non lo riconoscono, interpretano il contenuto con la codepage
   * locale anziche' con UTF-8, rendendo illeggibili gli accenti dei nomi.
   *
   * @param string $categoria Categoria a cui appartiene la pagina
   * @param string $azione Azione svolta dalla pagina
   * @param string $nomefile Nome del file da scaricare
   * @param array $dati Lista di dati tabellari da passare alla vista
   * @param array $info Lista di informazioni singole da passare alla vista
   *
   * @return Response Pagina di risposta
   */
  protected function renderCsv(string $categoria, string $azione, string $nomefile,
                               array $dati=[], array $info=[]): Response {
    $tema = $this->reqstack->getSession()->get('/APP/APP/tema', '');
    $template = ($tema ? $tema.'/' : '').$categoria.'/'.$azione.'.csv.twig';
    // antepone il BOM UTF-8 al contenuto
    $csv = "\xEF\xBB\xBF".$this->renderView($template, ['dati' => $dati, 'info' => $info]);
    // invia il documento
    $response = new Response($csv);
    $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nomefile);
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    return $response;
  }

  /**
   * Invia un allegato scaricabile o visualizzabile.
   *
   * La visualizzazione inline è consentita solo per i file che risultano realmente
   * in formato PDF: altri contenuti (es. pagine HTML con estensione ".pdf") vengono
   * forzati come download, per non poter essere eseguiti nell'origine dell'applicazione.
   *
   * @param string $nomefile Percorso completo del file da inviare
   * @param string $nome Nome da mostrare nel download
   * @param bool $inline Vero se l'utente ha richiesto la visualizzazione inline
   *
   * @return Response Risposta con il file allegato
   */
  protected function rispostaAllegato(string $nomefile, string $nome, bool $inline): Response {
    // inline solo per i PDF effettivamente validati
    $inline = $inline && $this->isPdf($nomefile);
    $response = $this->file($nomefile, $nome, $inline ?
      ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    // impedisce l'interpretazione del contenuto in base all'estensione
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Content-Type', $inline ? 'application/pdf' : 'application/octet-stream');
    return $response;
  }

  /**
   * Controlla se il file indicato è realmente un PDF.
   *
   * @param string $nomefile Percorso completo del file da controllare
   *
   * @return bool Vero se il contenuto del file è in formato PDF
   */
  private function isPdf(string $nomefile): bool {
    if (!is_file($nomefile)) {
      return false;
    }
    $tipo = '';
    if (class_exists('finfo')) {
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      $tipo = (string) $finfo->file($nomefile);
    } else {
      $tipo = (string) @mime_content_type($nomefile);
    }
    return $tipo === 'application/pdf';
  }

}
