<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use TCPDF;
use Exception;
use Qipsius\TCPDFBundle\Controller\TCPDFController;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;


/**
 * PdfManager - classe di utilità per la gestione dei documenti PDF
 *
 * @author Antonello Dessì
 */
class PdfManager {


  //==================== METODI DELLA CLASSE ====================
  /**
   * Costruttore
   *
   * @param TCPDFController $pdfcontroller Controlla la creazione dell'oggetto TCPDF
   * @param TCPDF|null $pdf Gestore dei documenti in formato PDF
   */
  public function __construct(
      private readonly TCPDFController $pdfcontroller,
      private ?TCPDF $pdf = null) {
  }

  /**
   * Configura un nuovo documento PDF con le impostazioni di base
   *
   * @param string $author Autore del documento
   * @param string $title Titolo del documento
   * @param bool $pdfa_mode Se vero genera un documento in formato PDF/A, se falso un PDF normale
   */
  public function configure(string $author, string $title, bool $pdfa_mode = false) {
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
  public function createFromHtml(string $html) {
    // trasforma in PDF
    $this->pdf->AddPage();
    $this->pdf->writeHTML($html);
  }

  /**
   * Invia al browser il file PDF creato
   *
   * @param string $filename Nome del file da inviare al browser
   * @param string $mode Modo di invio al browser: I=inline, D=download
   *
   * @return Response Pagina di risposta
   */
  public function send(string $filename, string $mode = 'D'): Response {
    $doc = $this->pdf->Output('', 'S');
    $disposition = HeaderUtils::makeDisposition($mode == 'I' ? HeaderUtils::DISPOSITION_INLINE :
      HeaderUtils::DISPOSITION_ATTACHMENT, $filename);
    $response = new Response($doc);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', $disposition);
    return $response;
  }

  /**
   * Salva localmente sul server il file PDF creato
   *
   * @param string $filename Nome del file da salvare
   */
  public function save(string $filename) {
    $this->pdf->lastPage();
    $this->pdf->Output($filename, 'F');
  }

  /**
   * Restituisce il gestore del documento PDF
   *
   * @return TCPDF|null Restituisce il gestore del documento
   */
  public function getHandler(): ?TCPDF {
    return $this->pdf;
  }

  /**
   * Importa un documento PDF esistente
   *
   * @param string $file Percorso completo del file PDF da importare
   * @param int $footer Altezza del piè di pagina da cancellare in mm (default=0, nessuna cancellazione)
   *
   * @return bool Vero se importazione è avvenuta correttamente, falso altrimenti
   */
  public function import(string $file, int $footer=0): bool {
    if (strtolower(substr($file, -4)) != '.pdf') {
      // non è un documento PDF
      return false;
    }
    $this->pdf = new Fpdi();
    try {
      // importa file e calcola il numero pagine del documento
      $pageCount = $this->pdf->setSourceFile($file);
    } catch (Exception) {
      // documento illegibile o protetto: converte file PDF
      if (!$this->convertFormat($file)) {
        // errore nella conversione
        return false;
      }
      $this->pdf = new Fpdi();
      try {
        // riprova l'importazione
        $pageCount = $this->pdf->setSourceFile($file);
      } catch (Exception) {
        // errore nella codifica
        return false;
      }
    }
    // layout documento
    $this->pdf->SetMargins(15, 20, 15, true);
    $this->pdf->SetAutoPageBreak(true, 20);
    $this->pdf->setPrintHeader(false);
    $this->pdf->setPrintFooter(false);
    // font predefinito
    $this->pdf->SetFont('times', '', 12);
    // importa tutto il documento
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
      // importa una pagina
      $templateId = $this->pdf->importPage($pageNo);
      $this->pdf->AddPage();
      // imposta le dimensioni della pagina importata
      $this->pdf->useTemplate($templateId, ['adjustPageSize' => true]);
      if ($footer > 0) {
        $this->pdf->SetFillColor(255, 255, 255);
        $this->pdf->Rect(0, $this->pdf->getPageHeight() - $footer, $this->pdf->getPageWidth(), $footer, 'F');
        // PDF: footer
        $this->pdf->SetFooterMargin(10);
        $this->pdf->setFooterFont(['helvetica', '', 9]);
        $this->pdf->setFooterData([0, 0, 0], [255, 255, 255]);
        $this->pdf->setPrintFooter(true);
      }
    }
    // importazione eseguita
    return true;
  }

  /**
   * Protegge il documento con password
   *
   * @param string $password Password usata per la protezione del documento
   */
  public function protect(string $password) {
    $this->pdf->SetProtection(
      ['print', 'modify', 'copy', 'annot-forms', 'fill-forms', 'extract', 'assemble', 'print-high'],
      $password, null, 3);
  }

  /**
   * Converte il documento PDF in un formato compatibile
   *
   * @param string $file Percorso completo del file PDF da convertire
   *
   * @return bool Vero se la conversione è avvenuta correttamente, falso altrimenti
   */
  public function convertFormat(string $file): bool {
    try {
      $proc = new Process(['/usr/bin/unoconv', '-f', 'pdf', '-d', 'document', '-o', $file.'.pdf', $file]);
      $proc->setTimeout(0);
      $proc->run();
      if ($proc->isSuccessful() && file_exists($file.'.pdf')) {
        // conversione ok: cancella vecchio file e rinomina nuovo
        unlink($file);
        rename($file.'.pdf', $file);
        return true;
      }
    } catch (Exception) {
      // errore: non fa niente
    }
    // errore: restituisce falso
    return false;
  }

  /**
   * Restituisce il nome di file normalizzato in maiuscolo
   *
   * @param string $nome Nome di file da normalizzare
   *
   * @return string Nome di file normalizzato
   */
  public function normalizzaNome($nome) {
    $testo = mb_strtoupper($nome, 'UTF-8');
    $testo = str_replace(['À', 'È', 'É', 'Ì', 'Ò', 'Ù'], ['A', 'E', 'E', 'I', 'O', 'U'], $testo);
    $testo = preg_replace('/\W+/','-', $testo);
    if (str_ends_with((string) $testo, '-')) {
      $testo = substr((string) $testo, 0, -1);
    }
    return $testo;
  }

}
