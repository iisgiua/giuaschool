<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Controller;

use App\Controller\ApiController;
use App\Entity\AutenticazioneDispositivo;
use App\Entity\Configurazione;
use App\Entity\Utente;
use App\Security\AuthConnectAuthenticator;
use App\Tests\DatabaseTestCase;
use App\Util\ConfigLoader;
use App\Util\LogHandler;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Uid\Uuid;


/**
 * Unit test delle API di autenticazione tramite dispositivo
 *
 * @author Antonello Dessì
 */
class ApiAuthControllerTest extends DatabaseTestCase {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var LoggerInterface $logger Gestore dei log su file (mocked)
   */
  private LoggerInterface $logger;

  /**
   * @var LogHandler $dblogger Gestore dei log su database (mocked)
   */
  private LogHandler $dblogger;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Predispone i servizi per l'ambiente di test
   *
   */
  protected function setUp(): void {
    // dati da caricare
    $this->fixtures = ['AmministratoreFixtures', 'AtaFixtures', 'ConfigurazioneFixtures',
      'DocenteFixtures', 'GenitoreFixtures', 'PresideFixtures', 'StaffFixtures', 'UtenteFixtures'];
    // esegue il setup standard
    parent::setUp();
  }

  /**
   * Crea le istanze fittizie per altri servizi
   *
   */
  protected function mockServices(): void {
    // logger: non registra i messaggi
    $this->logger = $this->createMock(LoggerInterface::class);
    // logHandler: non registra le azioni
    $this->dblogger = $this->createMock(LogHandler::class);
  }

  /**
   * Test della funzione authRequest.
   *
   */
  public function testAuthRequest(): void {
    $controller = $this->creaController();
    // ID dispositivo mancante
    $res = $controller->authRequest($this->creaRequest([]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    // dispositivo inesistente
    $res = $controller->authRequest($this->creaRequest(['dispositivoId' => 'dispositivo-inesistente']),
      $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    // utente disabilitato
    $utente = $this->getReference('docente_curricolare_1');
    [, $chiavePubblica] = $this->creaChiavi();
    $idDispositivo = $this->impostaDispositivo($utente, $chiavePubblica);
    $utente->setAbilitato(false);
    $this->em->flush();
    $res = $controller->authRequest($this->creaRequest(['dispositivoId' => $idDispositivo]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    // dispositivo scaduto
    $utente->setAbilitato(true);
    $utente->setDispositivoRegistrato(new DateTimeImmutable('-200 days'));
    $this->em->flush();
    $res = $controller->authRequest($this->creaRequest(['dispositivoId' => $idDispositivo]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    $this->assertSame('DISPOSITIVO_NON_VALIDO', $this->datiRisposta($res)['errore'] ?? null);
    // dispositivo senza chiave pubblica
    $utente->setDispositivoRegistrato(new DateTimeImmutable());
    $utente->setDispositivoChiave(null);
    $this->em->flush();
    $res = $controller->authRequest($this->creaRequest(['dispositivoId' => $idDispositivo]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    $this->assertSame('DISPOSITIVO_NON_VALIDO', $this->datiRisposta($res)['errore'] ?? null);
    // dispositivo valido
    $utente->setDispositivoChiave($chiavePubblica);
    $this->em->flush();
    $res = $controller->authRequest($this->creaRequest(['dispositivoId' => $idDispositivo]), $this->logger);
    $this->assertSame(Response::HTTP_OK, $res->getStatusCode());
    $dati = $this->datiRisposta($res);
    $this->assertArrayHasKey('id', $dati);
    $this->assertArrayHasKey('casuale', $dati);
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $dati['id']);
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $dati['casuale']);
    $autenticazione = $this->em->getRepository(AutenticazioneDispositivo::class)
      ->findOneBy(['idPubblico' => $dati['id']]);
    $this->assertNotNull($autenticazione);
    $this->assertSame($utente->getId(), $autenticazione->getUtente()->getId());
    $this->assertFalse($autenticazione->getRichiestaUsata());
    $adesso = new DateTimeImmutable();
    $this->assertGreaterThanOrEqual($adesso->modify('+58 seconds'),
      $autenticazione->getScadenzaRichiesta());
    $this->assertLessThanOrEqual($adesso->modify('+62 seconds'),
      $autenticazione->getScadenzaRichiesta());
  }

  /**
   * Test della funzione authRegister.
   *
   */
  public function testAuthRegister(): void {
    $utente = $this->getReference('docente_curricolare_1');
    $controller = $this->creaController($utente);
    $this->dblogger->expects($this->exactly(2))->method('logAzione')
      ->with('ACCESSO', 'Registrazione dispositivo', ['utente' => $utente->getUserIdentifier()]);
    // chiave pubblica mancante
    $res = $controller->authRegister($this->creaRequest([]), $this->logger, $this->dblogger);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $res->getStatusCode());
    $this->assertNull($utente->getDispositivoId());
    // chiave pubblica non valida
    $res = $controller->authRegister($this->creaRequest(['chiavePubblica' => 'AAAA']), $this->logger,
      $this->dblogger);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $res->getStatusCode());
    $this->assertNull($utente->getDispositivoId());
    // chiave pubblica valida
    [, , $chiaveBody1] = $this->creaChiavi();
    $res = $controller->authRegister($this->creaRequest(['chiavePubblica' => $chiaveBody1]), $this->logger,
      $this->dblogger);
    $this->assertSame(Response::HTTP_OK, $res->getStatusCode());
    $idDispositivo1 = $this->datiRisposta($res)['dispositivoId'] ?? null;
    $this->assertIsString($idDispositivo1);
    $this->assertTrue(Uuid::isValid($idDispositivo1));
    $this->assertSame($idDispositivo1, $utente->getDispositivoId());
    $this->assertNotFalse(openssl_pkey_get_public($utente->getDispositivoChiave()));
    $this->assertNotNull($utente->getDispositivoRegistrato());
    // sostituzione del dispositivo
    [, , $chiaveBody2] = $this->creaChiavi();
    $res = $controller->authRegister($this->creaRequest(['chiavePubblica' => $chiaveBody2]), $this->logger,
      $this->dblogger);
    $this->assertSame(Response::HTTP_OK, $res->getStatusCode());
    $idDispositivo2 = $this->datiRisposta($res)['dispositivoId'] ?? null;
    $this->assertIsString($idDispositivo2);
    $this->assertTrue(Uuid::isValid($idDispositivo2));
    $this->assertNotSame($idDispositivo1, $idDispositivo2);
    $this->assertSame($idDispositivo2, $utente->getDispositivoId());
    $this->assertNotFalse(openssl_pkey_get_public($utente->getDispositivoChiave()));
  }

  /**
   * Test della funzione authValidate.
   *
   */
  public function testAuthValidate(): void {
    $controller = $this->creaController();
    [$chiavePrivata, $chiavePubblica] = $this->creaChiavi();
    $utente = $this->getReference('docente_curricolare_1');
    $this->impostaDispositivo($utente, $chiavePubblica);
    // richiesta inesistente
    $res = $controller->authValidate($this->creaRequest(['id' => 'richiesta-inesistente',
      'firma' => base64_encode('firma')]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    // richiesta scaduta
    $autenticazione = $this->creaChallenge($utente, new DateTimeImmutable('-1 second'));
    $res = $controller->authValidate($this->creaRequest(['id' => $autenticazione->getIdPubblico(),
      'firma' => base64_encode('firma')]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    $this->em->refresh($autenticazione);
    $this->assertFalse($autenticazione->getRichiestaUsata());
    // richiesta gia' usata
    $autenticazione = $this->creaChallenge($utente, new DateTimeImmutable('+60 seconds'), true);
    $res = $controller->authValidate($this->creaRequest(['id' => $autenticazione->getIdPubblico(),
      'firma' => base64_encode('firma')]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    // dispositivo non valido
    $utenteSenzaDispositivo = $this->getReference('docente_curricolare_2');
    $utenteSenzaDispositivo->setDispositivoId(null)->setDispositivoChiave(null)
      ->setDispositivoRegistrato(null);
    $this->em->flush();
    $autenticazione = $this->creaChallenge($utenteSenzaDispositivo);
    $res = $controller->authValidate($this->creaRequest(['id' => $autenticazione->getIdPubblico(),
      'firma' => base64_encode('firma')]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    $this->em->refresh($autenticazione);
    $this->assertTrue($autenticazione->getRichiestaUsata());
    // firma non codificata in base64
    $autenticazione = $this->creaChallenge($utente);
    $res = $controller->authValidate($this->creaRequest(['id' => $autenticazione->getIdPubblico(),
      'firma' => '@@@']), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    $this->em->refresh($autenticazione);
    $this->assertTrue($autenticazione->getRichiestaUsata());
    // chiave pubblica del dispositivo non valida
    $utenteChiaveInvalida = $this->getReference('docente_curricolare_3');
    $utenteChiaveInvalida->setDispositivoId('dispositivo-chiave-invalida')
      ->setDispositivoChiave('chiave non valida')
      ->setDispositivoRegistrato(new DateTimeImmutable());
    $this->em->flush();
    $autenticazione = $this->creaChallenge($utenteChiaveInvalida);
    $res = $controller->authValidate($this->creaRequest(['id' => $autenticazione->getIdPubblico(),
      'firma' => base64_encode('firma')]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    $this->em->refresh($autenticazione);
    $this->assertTrue($autenticazione->getRichiestaUsata());
    // firma non valida
    $autenticazione = $this->creaChallenge($utente);
    $res = $controller->authValidate($this->creaRequest(['id' => $autenticazione->getIdPubblico(),
      'firma' => base64_encode('firma-errata')]), $this->logger);
    $this->assertSame(Response::HTTP_NOT_FOUND, $res->getStatusCode());
    $this->em->refresh($autenticazione);
    $this->assertTrue($autenticazione->getRichiestaUsata());
    // firma valida
    $autenticazione = $this->creaChallenge($utente);
    $firma = $this->firma($autenticazione, $chiavePrivata);
    $res = $controller->authValidate($this->creaRequest(['id' => $autenticazione->getIdPubblico(),
      'firma' => $firma]), $this->logger);
    $this->assertSame(Response::HTTP_OK, $res->getStatusCode());
    $codice = $this->datiRisposta($res)['codice'] ?? null;
    $this->assertIsString($codice);
    $this->assertNotSame('', $codice);
    $this->em->refresh($autenticazione);
    $this->assertSame($codice, $autenticazione->getToken());
    $this->assertTrue($autenticazione->getRichiestaUsata());
    $this->assertFalse($autenticazione->getTokenUsato());
    $this->assertNotNull($autenticazione->getScadenzaToken());
    $adesso = new DateTimeImmutable();
    $this->assertGreaterThanOrEqual($adesso->modify('+28 seconds'),
      $autenticazione->getScadenzaToken());
    $this->assertLessThanOrEqual($adesso->modify('+32 seconds'),
      $autenticazione->getScadenzaToken());
  }

  /**
   * Test della funzione authRevoke.
   *
   */
  public function testAuthRevoke(): void {
    $utente = $this->getReference('docente_curricolare_1');
    [, $chiavePubblica] = $this->creaChiavi();
    $idDispositivo = $this->impostaDispositivo($utente, $chiavePubblica);
    $controller = $this->creaController($utente);
    $this->dblogger->expects($this->once())->method('logAzione')
      ->with('ACCESSO', 'Revoca dispositivo', ['utente' => $utente->getUserIdentifier()]);
    // ID dispositivo mancante
    $res = $controller->authRevoke($this->creaRequest([]), $this->logger, $this->dblogger);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $res->getStatusCode());
    $this->assertSame($idDispositivo, $utente->getDispositivoId());
    // ID dispositivo errato
    $res = $controller->authRevoke($this->creaRequest(['dispositivoId' => 'dispositivo-errato']),
      $this->logger, $this->dblogger);
    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $res->getStatusCode());
    $this->assertSame($idDispositivo, $utente->getDispositivoId());
    // revoca corretta
    $res = $controller->authRevoke($this->creaRequest(['dispositivoId' => $idDispositivo]), $this->logger,
      $this->dblogger);
    $this->assertSame(Response::HTTP_OK, $res->getStatusCode());
    $this->assertNull($utente->getDispositivoId());
    $this->assertNull($utente->getDispositivoChiave());
    $this->assertNull($utente->getDispositivoRegistrato());
  }

  /**
   * Test del flusso completo di registrazione e autenticazione del dispositivo.
   *
   */
  public function testFlussoCompleto(): void {
    $utente = $this->getReference('docente_curricolare_4');
    [$chiavePrivata, , $chiaveBody] = $this->creaChiavi();
    $controller = $this->creaController($utente);
    $this->dblogger->expects($this->once())->method('logAzione')
      ->with('ACCESSO', 'Registrazione dispositivo', ['utente' => $utente->getUserIdentifier()]);
    // registrazione del dispositivo
    $res = $controller->authRegister($this->creaRequest(['chiavePubblica' => $chiaveBody]), $this->logger,
      $this->dblogger);
    $this->assertSame(Response::HTTP_OK, $res->getStatusCode());
    $idDispositivo = $this->datiRisposta($res)['dispositivoId'] ?? null;
    $this->assertIsString($idDispositivo);
    // richiesta di autenticazione
    $res = $controller->authRequest($this->creaRequest(['dispositivoId' => $idDispositivo]), $this->logger);
    $this->assertSame(Response::HTTP_OK, $res->getStatusCode());
    $richiesta = $this->datiRisposta($res);
    $autenticazione = $this->em->getRepository(AutenticazioneDispositivo::class)
      ->findOneBy(['idPubblico' => $richiesta['id']]);
    $this->assertNotNull($autenticazione);
    // validazione della firma
    $firma = $this->firma($autenticazione, $chiavePrivata);
    $res = $controller->authValidate($this->creaRequest(['id' => $richiesta['id'], 'firma' => $firma]),
      $this->logger);
    $this->assertSame(Response::HTTP_OK, $res->getStatusCode());
    $codice = $this->datiRisposta($res)['codice'] ?? null;
    $this->assertIsString($codice);
    // connessione tramite il token monouso
    $router = $this->createMock(RouterInterface::class);
    $router->method('generate')->willReturn('login_home');
    $config = $this->createMock(ConfigLoader::class);
    $authenticator = new AuthConnectAuthenticator($this->em, $router, $this->logger, $this->dblogger,
      $config);
    $risultato = $authenticator->getUser($codice, ['ip' => '127.0.0.1']);
    $this->assertInstanceOf(Utente::class, $risultato);
    $this->assertSame($utente->getId(), $risultato->getId());
    $this->em->refresh($autenticazione);
    $this->assertTrue($autenticazione->getTokenUsato());
    // il token non può essere riutilizzato
    try {
      $exception = null;
      $authenticator->getUser($codice, ['ip' => '127.0.0.1']);
    } catch (CustomUserMessageAuthenticationException $exception) {
    }
    $this->assertInstanceOf(CustomUserMessageAuthenticationException::class, $exception);
    $this->assertSame('exception.api_auth.richiesta_invalida', $exception->getMessage());
  }

  /**
   * Crea un'istanza del controller con l'utente eventualmente autenticato.
   *
   * @param Utente|null $utente Utente autenticato
   *
   * @return ApiController Controller configurato
   */
  private function creaController(?Utente $utente = null): ApiController {
    $controller = new ApiController($this->em, new RequestStack());
    if ($utente !== null) {
      $token = new UsernamePasswordToken($utente, 'main', $utente->getRoles());
      $storage = new TokenStorage();
      $storage->setToken($token);
      $container = new Container();
      $container->set('security.token_storage', $storage);
      $controller->setContainer($container);
    }
    return $controller;
  }

  /**
   * Crea una richiesta con contenuto JSON.
   *
   * @param array $dati Dati da inviare
   *
   * @return Request Richiesta creata
   */
  private function creaRequest(array $dati): Request {
    return new Request([], [], [], [], [], [], json_encode($dati, JSON_THROW_ON_ERROR));
  }

  /**
   * Restituisce i dati di una risposta JSON.
   *
   * @param Response $response Risposta da decodificare
   *
   * @return array Dati decodificati
   */
  private function datiRisposta(Response $response): array {
    $dati = json_decode((string) $response->getContent(), true);
    $this->assertIsArray($dati);
    return $dati;
  }

  /**
   * Crea una coppia di chiavi per il dispositivo.
   *
   * @return array Coppia di chiavi e chiave pubblica in formato PEM e base64
   */
  private function creaChiavi(): array {
    $chiavePrivata = openssl_pkey_new(['private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $this->assertNotFalse($chiavePrivata);
    $dettagli = openssl_pkey_get_details($chiavePrivata);
    $this->assertIsArray($dettagli);
    $chiavePubblica = $dettagli['key'];
    $chiaveBody = (string) preg_replace('/-----[^-]+-----|\s+/', '', $chiavePubblica);
    return [$chiavePrivata, $chiavePubblica, $chiaveBody];
  }

  /**
   * Associa un dispositivo valido all'utente.
   *
   * @param Utente $utente Utente da modificare
   * @param string $chiavePubblica Chiave pubblica in formato PEM
   * @param DateTimeImmutable|null $registrato Data di registrazione
   *
   * @return string ID del dispositivo
   */
  private function impostaDispositivo(Utente $utente, string $chiavePubblica,
                                      ?DateTimeImmutable $registrato = null): string {
    $idDispositivo = 'dispositivo-'.bin2hex(random_bytes(8));
    $utente->setDispositivoId($idDispositivo)
      ->setDispositivoChiave($chiavePubblica)
      ->setDispositivoRegistrato($registrato ?? new DateTimeImmutable());
    $this->em->flush();
    return $idDispositivo;
  }

  /**
   * Crea una richiesta di autenticazione (challenge) per l'utente.
   *
   * @param Utente $utente Utente associato
   * @param DateTimeImmutable|null $scadenza Scadenza della richiesta
   * @param bool $usata Indica se la richiesta è già stata usata
   *
   * @return AutenticazioneDispositivo Richiesta creata
   */
  private function creaChallenge(Utente $utente, ?DateTimeImmutable $scadenza = null,
                                 bool $usata = false): AutenticazioneDispositivo {
    $autenticazione = (new AutenticazioneDispositivo())
      ->setIdPubblico(bin2hex(random_bytes(32)))
      ->setUtente($utente)
      ->setCasuale(bin2hex(random_bytes(32)))
      ->setScadenzaRichiesta($scadenza ?? new DateTimeImmutable('+60 seconds'))
      ->setRichiestaUsata($usata);
    $this->em->persist($autenticazione);
    $this->em->flush();
    return $autenticazione;
  }

  /**
   * Firma la richiesta di autenticazione con la chiave privata.
   *
   * @param AutenticazioneDispositivo $autenticazione Richiesta da firmare
   * @param mixed $chiavePrivata Chiave privata del dispositivo
   *
   * @return string Firma codificata in base64
   */
  private function firma(AutenticazioneDispositivo $autenticazione, mixed $chiavePrivata): string {
    $protocollo = $this->em->getRepository(Configurazione::class)->getParametro('protocollo_app', '');
    $firma = '';
    $this->assertTrue(openssl_sign($protocollo."\n".$autenticazione->getIdPubblico()."\n".
      $autenticazione->getCasuale(), $firma, $chiavePrivata, OPENSSL_ALGO_SHA256));
    return base64_encode($firma);
  }

}
