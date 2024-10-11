<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use DateTime;
use App\Entity\Classe;
use App\Entity\DefinizioneRichiesta;
use App\Entity\Utente;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;


/**
 * RichiesteUtil - classe di utilità per la gestione dei moduli di richiesta
 *
 * @author Antonello Dessì
 */
class RichiesteUtil {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param PdfManager $pdf Gestore dei documenti PDF
   * @param RequestStack $reqstack Gestore dello stack delle variabili globali
   * @param Environment $tpl Gestione template
   * @param string $dirProgetto Percorso della directory di progetto
   */
  public function __construct(
      private readonly PdfManager $pdf,
      private readonly RequestStack $reqstack,
      private readonly Environment $tpl,
      private readonly string $dirProgetto)
  {
  }

  /**
   * Crea il documento PDF a partire dal modulo di richiesta e lo salva nell'archivio dei documenti di classe.
   *
   * @param DefinizioneRichiesta $definizioneRichiesta Definizione del modulo di richiesta
   * @param Utente $utente Utente che esegue la richiesta
   * @param Classe $classe Classe di riferimento per la richiesta
   * @param array $valori Lista dei valori inseriti nel modulo di richiesta
   * @param DateTime|null $data Data della richiesta
   * @param DateTime $invio Data e ora dell'invio della richiesta
   *
   * @return array Lista con il nome del documento PDF creato e l'id del documento
   */
  public function creaPdf(DefinizioneRichiesta $definizioneRichiesta, Utente $utente, Classe $classe,
                          array $valori, ?DateTime $data, DateTime $invio): array {
    // inizializza
    $fs = new FileSystem();
    $documentoId = $definizioneRichiesta->getId().'-'.$utente->getId().'-'.uniqid();
    // crea template per il documento
    $template = file_get_contents($this->dirProgetto.'/PERSONAL/data/moduli/'.$definizioneRichiesta->getModulo());
    foreach ($definizioneRichiesta->getCampi() as $nome => $campo) {
      $template = match ($campo[0]) {
        // testo, aggiunge formattazione
        'text' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<em><strong>{{ valori.'.$nome.' ?? "---" }}</strong></em>', $template),
        // converte booleano in testo
        'bool' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? "SI" : "NO" }}</strong>', $template),
        // converte data in testo
        'date' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? (valori.'.$nome.'|date("d/m/Y")) : "---" }}</strong>', $template),
        // converte ora in testo
        'time' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? (valori.'.$nome.'|date("H:i")) : "---" }}</strong>', $template),
        // sostituzione con il valore
        default => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ?? "---" }}</strong>', $template),
      };
    }
    if (!$definizioneRichiesta->getUnica()) {
      // data della richiesta: converte data in testo
      $template = preg_replace('/\{\{\s*form_widget\(\s*form\.data\s*[^\)]*\)\s*\}\}/',
        '<strong>{{ data ? (data|date("d/m/Y")) : "---" }}</strong>', $template);
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
    $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
    $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
    $this->pdf->getHandler()->setPrintFooter(true);
    $this->pdf->createFromHtml($html);
    // salva il documento
    $percorso = $this->dirProgetto.'/FILES/archivio/classi/'.
      $classe->getAnno().$classe->getSezione().$classe->getGruppo().'/documenti';
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
   * @param Utente $utente Utente che esegue la richiesta
   * @param Classe $classe Classe di riferimento per la richiesta
   * @param string $documentoId Identificativo del documento
   * @param array $allegati Lista dei file allegati
   *
   * @return array Lista con i nomi dei file allegati
   */
  public function impostaAllegati(Utente $utente, Classe $classe, string $documentoId, array $allegati): array {
    // inizializza
    $fs = new FileSystem();
    $listaAllegati = [];
    // elabora i file
    $percorso = $this->dirProgetto.'/FILES/archivio/classi/'.
      $classe->getAnno().$classe->getSezione().$classe->getGruppo().'/documenti/';
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
