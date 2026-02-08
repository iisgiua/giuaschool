<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\EventListener;

use App\Entity\AssenzaLezione;
use App\Entity\ComunicazioneClasse;
use App\Entity\ComunicazioneUtente;
use App\Entity\Log;
use App\Entity\Provisioning;
use App\Entity\Spid;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;


/**
 * LogListener - gestione del log delle modifiche alle entità
 *
 * @author Antonello Dessì
 */
#[When(env: 'prod')]
#[When(env: 'dev')]
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class LogListener {


  //==================== ATTRIBUTI DELLA CLASSE ====================

  /**
   * @const ENTITA_ESCLUSE Lista delle entità da non considerare nel log
   */
  private const ENTITA_ESCLUSE = [
    AssenzaLezione::class => true,
    ComunicazioneClasse::class => true,
    ComunicazioneUtente::class => true,
    Log::class => true,
    Provisioning::class => true,
    Spid::class => true];

  /**
   * @const CAMPI_ESCLUSI Lista dei campi da escludere nel log delle modifiche
   */
  private const CAMPI_ESCLUSI = ['id', 'creato', 'modificato'];

  /**
   * @var array $info Informazioni aggiuntive per il log
   */
  private array $info = [];

  /**
   * @var array $codaPost Lista degli oggetti da processare con l'evento PostFlush
   */
  private array $codaPost = [];

  /**
   * @var array $codaLog Lista dei log da memorizzare sul database
   */
   private array $codaLog = [];

  /**
   * @var bool $attivo Stato del listener (necessario per bloccare chiamate ricorsive)
   */
   private bool $attivo = true;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RequestStack $request Coda delle pagine richieste
   * @param TokenStorageInterface $token Gestore dei dati di autenticazione
   */
  public function __construct(
      private EntityManagerInterface $em,
      private RequestStack $request,
      private TokenStorageInterface $token) {
  }

  /**
   * Listener per il log delle modifiche delle entità (eseguito prima del FLUSH)
   *
   * @param OnFlushEventArgs $args Argomenti passati da Doctrine al verificarsi dell'evento
   */
  public function onFlush(OnFlushEventArgs $args): void {
    if (!$this->attivo) {
      // evita chiamata ricorsiva
      return;
    }
    // inizializza
    $uow = $this->em->getUnitOfWork();
    $cont = 0;
    // inserimento nuovi dati
    foreach ($uow->getScheduledEntityInsertions() as $oggetto) {
      // esclude entità non rilevanti
      $nome = get_class($oggetto);
      if (isset(self::ENTITA_ESCLUSE[$nome])) {
        continue;
      }
      // conserva dati per il postFlush
      $this->codaPost[] = [$nome, $oggetto];
      $cont++;
    }
    // modifiche dati esistenti
    foreach ($uow->getScheduledEntityUpdates() as $oggetto) {
      // esclude entità non rilevanti
      $nome = get_class($oggetto);
      if (isset(self::ENTITA_ESCLUSE[$nome])) {
        continue;
      }
      // legge campi modificati
      $modificati = array_diff(array_keys($uow->getEntityChangeSet($oggetto)), self::CAMPI_ESCLUSI);
      if (!empty($modificati)) {
        // inserisce in log solo campi modificati
        $this->inserisceLog('U', $nome, $oggetto->getId(), $oggetto, $modificati);
        $cont++;
      }
    }
    // cancellazione dati esistenti
    foreach ($uow->getScheduledEntityDeletions() as $oggetto) {
      // esclude entità non rilevanti
      $nome = get_class($oggetto);
      if (isset(self::ENTITA_ESCLUSE[$nome])) {
        continue;
      }
      // inserisce in log solo ID
      $this->inserisceLog('D', $nome, $oggetto->getId());
      $cont++;
    }
    // legge le informazioni solo se necessario
    if ($cont > 0) {
      $req = $this->request->getCurrentRequest();
      $tok = $this->token->getToken();
      // dati utente
      $utente = $tok ? $tok->getUser() : null;
      $this->info['utente'] = $utente;
      $this->info['username'] = $utente ? $utente->getUserIdentifier() : '--ANONIMO--';
      $this->info['ruolo'] = $utente ? $utente->getRoles()[0] : '--NESSUNO--';
      $this->info['alias'] = ($tok instanceOf SwitchUserToken) ?
        $tok->getOriginalToken()->getUser()->getUserIdentifier() : null;
      // dati di navigazione
      $this->info['origine'] = $req ? $req->attributes->get('_controller') : '--COMMAND--';
      if (empty($this->info['origine']) && $req->getPathInfo() === '/logout/') {
        // caso particolare: per il logout non ci sono dati nel controller
        $this->info['origine'] = 'App\Controller\LoginController::logout';
      }
      $this->info['ip'] = $req ? $req->getClientIp() : '--CONSOLE--';
      $this->info['categoria'] = 'DATABASE';
    }
  }

  /**
   * Listener per il log delle modifiche delle entità (eseguito dopo il FLUSH)
   *
   * @param PostFlushEventArgs $args Argomenti passati da Doctrine al verificarsi dell'evento
   */
  public function postFlush(PostFlushEventArgs $args): void {
    if (!$this->attivo || empty($this->codaPost)) {
      // listener disattivato o nessun dato da inserire nel log
      return;
    }
    // processa gli oggetti nella lista
    $metadataCache = [];
    foreach ($this->codaPost as [$nome, $oggetto]) {
      $metadata = ($metadataCache[$nome] = $metadataCache[$nome] ?? $this->em->getClassMetadata($nome));
      // prende solo campi rilevanti
      $lista = [...$metadata->getFieldNames(), ...$metadata->getAssociationNames()];
      $campi = array_diff($lista, self::CAMPI_ESCLUSI);
      // inserisce tutti i dati
      $this->inserisceLog('C', $nome, $oggetto->getId(), $oggetto, $campi);
    }
    // svuota la lista degli oggetti da processare
    $this->codaPost = [];
  }

  /**
   * Restituisce la lista dei log da memorizzare sul database
   *
   * @return array Lista dei log da memorizzare sul database
   */
  public function leggeLog(): array {
    return $this->codaLog;
  }

  /**
   * Svuota la lista dei log da memorizzare sul database
   */
  public function svuotaLog(): void {
    $this->codaLog = [];
  }

  /**
   * Restituisce le informazioni aggiuntive per il log
   *
   * @return array Lista delle informazioni aggiuntive per il log
   */
  public function leggeInfo(): array {
    return $this->info;
  }

  /**
   * Disattiva il listener (evita chiamate ricorsive)
   */
  public function disattiva(): void {
    $this->attivo = false;
  }

  /**
   * Attiva il listener
   */
  public function attiva(): void {
    $this->attivo = true;
  }


  //==================== METODI PRIVATI DELLA CLASSE ====================

  /**
   * Inserisce i dati nella coda dei log da memorizzare sul database
   *
   * @param string $tipo Tipo di operazione [C=creazione, U=modifica, D=cancellazione]
   * @param string $nome Nome dell'entità da inserire nel log
   * @param int $id ID dell'oggetto da inserire nel log
   * @param object|null $oggetto Oggetto da inserire nel log
   * @param array $campi Lista dei campi da considerare per il log
   */
  private function inserisceLog(string $tipo, string $nome, int $id, object $oggetto=null, array $campi=[]): void {
    $dati = [];
    foreach ($campi as $campo) {
      $val = $oggetto->{'get'.ucfirst((string) $campo)}();
      // converte in formato scalare
      $dati[$campo] = match (true) {
        is_scalar($val), is_array($val), $val === null
          => $val,
        $val instanceOf DateTimeInterface
          => $val->format('Y-m-d H:i:s'),
        $val instanceOf Collection
          => array_map(fn($o) => $o->getId(), array_values($val->toArray())),
        is_object($val)
          => $val->getId(),
        default
          => json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)};
    }
    // inserisce nella coda di log
    $this->codaLog[] = [$tipo, $nome, $id, $dati];
  }

}
