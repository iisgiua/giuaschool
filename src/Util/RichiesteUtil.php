<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use App\Entity\Alunno;
use App\Entity\DefinizioneRichiesta;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;


/**
 * RichiesteUtil - classe di utilità per la gestione dei moduli di richiesta
 *
 * @author Antonello Dessì
 */
class RichiesteUtil {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var PdfManager $pdf Gestore dei documenti PDF
   */
  private PdfManager $pdf;

  /**
   * @var RequestStack $reqstack Gestore dello stack delle variabili globali
   */
  private RequestStack $reqstack;

  /**
   * @var Environment $tpl Gestione template
   */
  private Environment $tpl;

  /**
   * @var string $dirProgetto Percorso della directory di progetto
   */
  private string $dirProgetto;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param Environment $tpl Gestione template
   * @param string $dirProgetto Percorso della directory di progetto
   */
  public function __construct(PdfManager $pdf, RequestStack $reqstack, Environment $tpl, string $dirProgetto) {
    $this->pdf = $pdf;
    $this->reqstack = $reqstack;
    $this->tpl = $tpl;
    $this->dirProgetto = $dirProgetto;
  }

  /**
   * Crea il documento PDF a partire dal modulo di richiesta e lo salva nell'archivio dei documenti di classe.
   *
   * @param DefinizioneRichiesta $definizioneRichiesta Definizione del modulo di richiesta
   * @param Alunno $alunno Alunno a cui è riferita la richiesta
   * @param array $valori Lista dei valori inseriti nel modulo di richiesta
   * @param \DateTime|null $data Data della richiesta
   * @param \DateTime $invio Data e ora dell'invio della richiesta
   *
   * @return array Lista con il nome del documento PDF creato e l'id del documento
   */
  public function creaPdf(DefinizioneRichiesta $definizioneRichiesta, Alunno $alunno, array $valori,
                          ?\DateTime $data, \DateTime $invio): array {
    // inizializza
    $fs = new FileSystem();
    $documentoId = $definizioneRichiesta->getId().'-'.$alunno->getId().'-'.uniqid();
    // crea template per il documento
    $template = file_get_contents($this->dirProgetto.'/PERSONAL/data/moduli/'.$definizioneRichiesta->getModulo());
    foreach ($definizioneRichiesta->getCampi() as $nome => $campo) {
      switch ($campo[0]) {
        case 'text':
          // aggiunge formattazione
          $template = preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*\)\s*\}\}/',
            '<em><strong>{{ valori.'.$nome.' ?? "-----" }}</strong></em>', $template);
          break;
        case 'bool':
          // converte booleano in testo
          $template = preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*\)\s*\}\}/',
            '<strong>{{ valori.'.$nome.' ? "SI" : "NO" }}</strong>', $template);
          break;
        case 'date':
          // converte data in testo
          $template = preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*\)\s*\}\}/',
            '<strong>{{ valori.'.$nome.' ? (valori.'.$nome.'|date("d/m/Y")) : "-----" }}</strong>', $template);
          break;
        case 'time':
          // converte ora in testo
          $template = preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*\)\s*\}\}/',
            '<strong>{{ valori.'.$nome.' ? (valori.'.$nome.'|date("H:i")) : "-----" }}</strong>', $template);
          break;
        default:
          // sostituzione con il valore
          $template = preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*\)\s*\}\}/',
            '<strong>{{ valori.'.$nome.' ?? "-----" }}</strong>', $template);
      }
    }
    if (!$definizioneRichiesta->getUnica()) {
      // data della richiesta: converte data in testo
      $template = preg_replace('/\{\{\s*form_widget\(\s*form\.data\s*\)\s*\}\}/',
        '<strong>{{ data ? (data|date("d/m/Y")) : "-----" }}</strong>', $template);
    }
    $header = $fs->exists($this->dirProgetto.'/PERSONAL/templates/richieste/_intestazione_moduli.html.twig') ?
      $this->dirProgetto.'/PERSONAL/templates/richieste/_intestazione_moduli.html.twig' :
      $this->dirProgetto.'/templates/richieste/_intestazione_moduli.html.twig';
    $footer = $fs->exists($this->dirProgetto.'/PERSONAL/templates/richieste/_firma_moduli.html.twig') ?
      $this->dirProgetto.'/PERSONAL/templates/richieste/_firma_moduli.html.twig' :
      $this->dirProgetto.'/templates/richieste/_firma_moduli.html.twig';
    $template = file_get_contents($header).$template.file_get_contents($footer);
    $templateTwig = $this->tpl->createTemplate($template);
    // crea documento
    $html = $this->tpl->render($templateTwig, ['valori' => $valori, 'data' => $data, 'id' => $documentoId,
      'invio' => $invio]);
    $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      $definizioneRichiesta->getNome());
    $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
    $this->pdf->getHandler()->SetFooterMargin(10);
    $this->pdf->getHandler()->setFooterFont(Array('helvetica', '', 9));
    $this->pdf->getHandler()->setFooterData(array(0,0,0), array(255,255,255));
    $this->pdf->getHandler()->setPrintFooter(true);
    $this->pdf->createFromHtml($html);
    // salva il documento
    $percorso = $this->dirProgetto.'/FILES/archivio/classi/'.
      $alunno->getClasse()->getAnno().$alunno->getClasse()->getSezione().'/documenti';
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    $nomefile = 'modulo-'.$documentoId.'.pdf';
    $this->pdf->save($percorso.'/'.$nomefile);
    // restituisce il nome del file
    return [$nomefile, $documentoId];
  }

  /**
   * Imposta un allegato per il documento a cui appartiene
   *
   * @param Alunno $alunno Alunno a cui è riferita la richiesta
   * @param string $documentoId Identificativo del documento
   * @param array $allegati Lista dei file allegati
   *
   * @return array Lista con i nomi dei file allegati
   */
  public function impostaAllegati(Alunno $alunno, string $documentoId, array $allegati): array {
    // inizializza
    $fs = new FileSystem();
    $listaAllegati = [];
    // elabora i file
    $percorso = $this->dirProgetto.'/FILES/archivio/classi/'.
      $alunno->getClasse()->getAnno().$alunno->getClasse()->getSezione().'/documenti/';
    $percorsoTemp = $this->dirProgetto.'/FILES/tmp/';
    $num = 1;
    foreach ($allegati as $allegato) {
      $nomefile = 'modulo-'.$documentoId.'-allegato-'.$num.'.'.$allegato['ext'];
      // sposta e rinomina l'allegato
      $fs->rename($percorsoTemp.$allegato['temp'], $percorso.$nomefile);
      $listaAllegati[] = $nomefile;
      $num++;
    }
    // restituisce lista allegati
    return $listaAllegati;
  }

}
