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


namespace App\Util;

use Qipsius\TCPDFBundle\Controller\TCPDFController;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\Process\Process;


/**
 * PdfManager - classe di utilità per la gestione dei documenti PDF
 */
class PdfManager {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var TCPDFController $pdfcontroller Controlla la creazione dell'oggetto TCPDF
   */
  private $pdfcontroller;

  /**
   * @var TCPDF $pdf Gestore dei documenti in formato PDF
   */
  private $pdf;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Construttore
   *
   * @param TCPDFController $pdfcontroller Controlla la creazione dell'oggetto TCPDF
   */
  public function __construct(TCPDFController $pdfcontroller) {
    $this->pdfcontroller = $pdfcontroller;
    $this->pdf = null;
  }

  /**
   * Configura un nuovo documento PDF con le impostazioni di base
   *
   * @param string $author Autore del documento
   * @param string $title Titolo del documento
   * @param bool $pdfa_mode Se vero genera un documento in formato PDF/A, se falso un PDF normale
   */
  public function configure($author, $title, $pdfa_mode=false) {
    $this->pdf = $this->pdfcontroller->create('P', 'mm', 'A4', true, 'UTF-8', false, $pdfa_mode);
    // informazioni sul documento
    $this->pdf->SetCreator('TCPDF');
    $this->pdf->SetAuthor($author);
    $this->pdf->SetTitle($title);
    $this->pdf->SetSubject('');
    $this->pdf->SetKeywords('');
    // layout documento
    $this->pdf->SetMargins(15, 20, 15, true);
    $this->pdf->SetAutoPageBreak(true, 20);
    $this->pdf->setPrintHeader(false);
    $this->pdf->setPrintFooter(false);
    // font predefinito
    $this->pdf->SetFont('times', '', 12);
  }

  /**
   * Crea un documento PDF convertendolo dal codice HTML
   *
   * @param string $html Il testo in HTML da convertire
   */
  public function createFromHtml($html) {
    // trasforma in PDF
    $this->pdf->AddPage();
    $this->pdf->writeHTML($html);
  }

  /**
   * Invia al browser il file PDF creato
   *
   * @param string $filename Nome del file da inviare al browser
   */
  public function send($filename) {
    $this->pdf->lastPage();
    $this->pdf->Output($filename, 'D');
  }

  /**
   * Salva localmente sul server il file PDF creato
   *
   * @param string $filename Nome del file da salvare
   */
  public function save($filename) {
    $this->pdf->lastPage();
    $this->pdf->Output($filename, 'F');
  }

  /**
   * Restituisce il gestore del documento PDF
   *
   * @return TCPDF Restituisce il gestore del documento
   */
  public function getHandler() {
    return $this->pdf;
  }

  /**
   * Importa un documento PDF esistente
   *
   * @param string $file Percorso completo del file PDF da importare
   *
   * @return boolean Vero se importazione è avvenuta correttamente, falso altrimenti
   */
  public function import($file) {
    if (strtolower(substr($file, -4)) != '.pdf') {
      // non è un documento PDF
      return false;
    }
    $this->pdf = new Fpdi();
    try {
      // importa file e calcola il numero pagine del documento
      $pageCount = $this->pdf->setSourceFile($file);
    } catch (\Exception $e) {
      // documento illegibile o protetto: converte file PDF
      if (!$this->convertFormat($file)) {
        // errore nella conversione
        return false;
      }
      try {
        // riprova l'importazione
        $pageCount = $this->pdf->setSourceFile($file);
      } catch (\Exception $e) {
        // errore nella codifica
        return false;
      }
    }
    // importa tutto il documento
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
      // importa una pagina
      $templateId = $this->pdf->importPage($pageNo);
      $this->pdf->AddPage();
      // imposta le dimensioni della pagina importata
      $this->pdf->useTemplate($templateId, ['adjustPageSize' => true]);
    }
    // importazione eseguita
    return true;
  }

  /**
   * Protegge il documento con password
   *
   * @param string $password Password usata per la protezione del documento
   */
  public function protect($password) {
    $this->pdf->SetProtection(
      ['print', 'modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble', 'print-high'],
      $password, null, 3);
  }

  /**
   * Converte il documento PDF in un formato compatibile
   *
   * @param string $file Percorso completo del file PDF da convertire
   *
   * @return boolean Vero se la conversione è avvenuta correttamente, falso altrimenti
   */
  public function convertFormat($file) {
    try {
      $proc = new Process(['/usr/bin/unoconv', '-f', 'pdf', '-d', 'document', '-o', $file.'.pdf', $file]);
      if ($proc->isSuccessful() && file_exists($file.'.pdf')) {
        // conversione ok: cancella vecchio file e rinomina nuovo
        unlink($file);
        rename($file.'.pdf', $file);
        return true;
      }
    } catch (\Exception $err) {
      // errore: non fa niente
    }
    // errore: restituisce falso
    return false;
  }

}
