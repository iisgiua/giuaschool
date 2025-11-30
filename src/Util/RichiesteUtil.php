<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Util;

use DateTime;
use App\Entity\Classe;
use App\Entity\DefinizioneConsultazione;
use App\Entity\DefinizioneRichiesta;
use App\Entity\Utente;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Filesystem\Filesystem;
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
   * @param int $idUtente Identificativo dell'utente che esegue la richiesta
   * @param int $idRichiesta Identificativo della richiesta
   * @param Classe $classe Classe di riferimento per la richiesta
   * @param array $valori Lista dei valori inseriti nel modulo di richiesta
   * @param DateTime|null $data Data della richiesta
   * @param DateTime $invio Data e ora dell'invio della richiesta
   *
   * @return array Lista con il nome del documento PDF creato e l'id del documento
   */
  public function richiestaPdf(DefinizioneRichiesta $definizioneRichiesta, int $idUtente, int $idRichiesta,
                               Classe $classe, array $valori, ?DateTime $data, DateTime $invio): array {
    // inizializza
    $fs = new FileSystem();
    // crea template per il documento
    $percorso = $this->dirProgetto.'/PERSONAL/data/moduli/';
    $template = file_get_contents($percorso.$definizioneRichiesta->getModulo());
    foreach ($definizioneRichiesta->getCampi() as $nome => $campo) {
      $template = match ($campo[0]) {
        // tipo testo su più righe
        'text' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<em><strong>{{ (valori.'.$nome.'|trim) ? (valori.'.$nome.'|trim) : "---" }}</strong></em>', $template),
        // tipo bool
        'bool' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? "SI" : "NO" }}</strong>', $template),
        // tipo data
        'date' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? (valori.'.$nome.'|date("d/m/Y")) : "---" }}</strong>', $template),
        // tipo ora
        'time' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? (valori.'.$nome.'|date("H:i")) : "---" }}</strong>', $template),
        // testo semplice
        default => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ (valori.'.$nome.'|trim) ? (valori.'.$nome.'|trim) : "---" }}</strong>', $template),
      };
    }
    if (!$definizioneRichiesta->getUnica()) {
      // data della richiesta: converte data in testo
      $template = preg_replace('/\{\{\s*form_widget\(\s*form\.data\s*[^\)]*\)\s*\}\}/',
        '<strong>{{ data ? (data|date("d/m/Y")) : "---" }}</strong>', $template);
    }
    // aggiunge header e footer
    $header = $fs->exists($this->dirProgetto.'/PERSONAL/templates/richieste/_intestazione_moduli.html.twig') ?
      $this->dirProgetto.'/PERSONAL/templates/richieste/_intestazione_moduli.html.twig' :
      $this->dirProgetto.'/templates/richieste/_intestazione_moduli.html.twig';
    $footer = $fs->exists($this->dirProgetto.'/PERSONAL/templates/richieste/_firma_moduli.html.twig') ?
      $this->dirProgetto.'/PERSONAL/templates/richieste/_firma_moduli.html.twig' :
      $this->dirProgetto.'/templates/richieste/_firma_moduli.html.twig';
    $template = file_get_contents($header).$template.file_get_contents($footer);
    $templateTwig = $this->tpl->createTemplate($template);
    // crea documento
    $html = $this->tpl->render($templateTwig, ['valori' => $valori, 'data' => $data, 'invio' => $invio]);
    $documentoId = $definizioneRichiesta->getId().'-'.$idUtente.'-'.$idRichiesta.'-'.hash('sha256', $html);
    $templateHash = $this->tpl->createTemplate($html.
      '<p>&nbsp;</p><p style="font-size:9pt;text-align:right;">[{{ id }}]</p>');
    $htmlHash = $this->tpl->render($templateHash, ['id' => $documentoId]);
    $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      $definizioneRichiesta->getNome());
    $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
    $this->pdf->getHandler()->SetFooterMargin(10);
    $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
    $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
    $this->pdf->getHandler()->setPrintFooter(true);
    $this->pdf->createFromHtml($htmlHash);
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


  /**
   * Crea il documento PDF per la risposta ad una consultazione e lo salva nell'archivio dei documenti di classe.
   *
   * @param DefinizioneConsultazione $consultazione Definizione della consultazione
   * @param int $idUtente Identificativo dell'utente che esegue la risposta
   * @param int $idRichiesta Identificativo della risposta
   * @param Classe $classe Classe di riferimento per la risposta
   * @param array $valori Lista dei valori inseriti nel modulo di richiesta
   * @param DateTime $invio Data e ora dell'invio della richiesta
   *
   * @return string Nnome del documento PDF creato
   */
  public function rispostaPdf(DefinizioneConsultazione $consultazione, int $idUtente, int $idRichiesta,
                              Classe $classe, array $valori, DateTime $invio): string {
    // inizializza
    $fs = new FileSystem();
    // crea template per il documento
    $percorso = $this->dirProgetto.'/PERSONAL/data/consultazioni/';
    $template = file_get_contents($percorso.$consultazione->getModulo());
    foreach ($consultazione->getCampi() as $nome => $campo) {
      $template = match ($campo[0]) {
        // tipo testo su più righe
        'text' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<em><strong>{{ (valori.'.$nome.'|trim) ? (valori.'.$nome.'|trim) : "---" }}</strong></em>', $template),
        // tipo bool
        'bool' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? "SI" : "NO" }}</strong>', $template),
        // tipo data
        'date' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? (valori.'.$nome.'|date("d/m/Y")) : "---" }}</strong>', $template),
        // tipo ora
        'time' => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ valori.'.$nome.' ? (valori.'.$nome.'|date("H:i")) : "---" }}</strong>', $template),
        // testo semplice
        default => preg_replace('/\{\{\s*form_widget\(\s*form\.'.$nome.'\s*[^\)]*\)\s*\}\}/',
          '<strong>{{ (valori.'.$nome.'|trim) ? (valori.'.$nome.'|trim) : "---" }}</strong>', $template),
      };
    }
    // aggiunge header e footer
    $header = $fs->exists($this->dirProgetto.'/PERSONAL/templates/richieste/_intestazione_consultazioni.html.twig') ?
      $this->dirProgetto.'/PERSONAL/templates/richieste/_intestazione_consultazioni.html.twig' :
      $this->dirProgetto.'/templates/richieste/_intestazione_consultazioni.html.twig';
    $footer = $fs->exists($this->dirProgetto.'/PERSONAL/templates/richieste/_firma_consultazioni.html.twig') ?
      $this->dirProgetto.'/PERSONAL/templates/richieste/_firma_consultazioni.html.twig' :
      $this->dirProgetto.'/templates/richieste/_firma_consultazioni.html.twig';
    $template = file_get_contents($header).$template.file_get_contents($footer);
    $templateTwig = $this->tpl->createTemplate($template);
    // crea documento
    $html = $this->tpl->render($templateTwig, ['valori' => $valori, 'invio' => $invio]);
    $documentoId = $consultazione->getId().'-'.$idUtente.'-'.$idRichiesta.'-'.hash('sha256', $html);
    $templateHash = $this->tpl->createTemplate($html.
      '<p>&nbsp;</p><p style="font-size:9pt;text-align:right;">[{{ id }}]</p>');
    $htmlHash = $this->tpl->render($templateHash, ['id' => $documentoId]);
    $this->pdf->configure($this->reqstack->getSession()->get('/CONFIG/ISTITUTO/intestazione'),
      $consultazione->getNome());
    $this->pdf->getHandler()->SetAutoPageBreak(true, 20);
    $this->pdf->getHandler()->SetFooterMargin(10);
    $this->pdf->getHandler()->setFooterFont(['helvetica', '', 9]);
    $this->pdf->getHandler()->setFooterData([0, 0, 0], [255, 255, 255]);
    $this->pdf->getHandler()->setPrintFooter(true);
    $this->pdf->createFromHtml($htmlHash);
    // salva il documento
    $percorso = $this->dirProgetto.'/FILES/archivio/classi/'.
      $classe->getAnno().$classe->getSezione().$classe->getGruppo().'/documenti';
    if (!$fs->exists($percorso)) {
      // crea directory
      $fs->mkdir($percorso, 0775);
    }
    $nomefile = 'consultazione-'.$documentoId.'.pdf';
    $this->pdf->save($percorso.'/'.$nomefile);
    // restituisce il nome del file
    return $nomefile;
  }

}
