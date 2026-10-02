# Architettura — giuaschool

Documento di riferimento per chi sviluppa sul progetto. Descrive come è organizzato il codice,
come è modellato il dominio, e dove si trovano i punti di estensione.

Per setup, comandi e convenzioni vedi il [README](../README.md).

---

## Indice

- [Visione d'insieme](#visione-dinsieme)
- [Il dominio](#il-dominio)
  - [Utenti e ruoli](#utenti-e-ruoli)
  - [Organizzazione scolastica](#organizzazione-scolastica)
  - [Lezioni, presenze e assenze](#lezioni-presenze-eassenze)
  - [Valutazioni e scrutini](#valutazioni-e-scrutini)
  - [Comunicazioni](#comunicazioni)
  - [Documenti](#documenti)
  - [Configurazione e menu](#configurazione-e-menu)
- [Strati applicativi](#strati-applicativi)
- [Configurazione in sessione](#configurazione-in-sessione)
- [Rendering](#rendering)
- [Notifiche](#notifiche)
- [Log e audit](#log-e-audit)
- [Sicurezza](#sicurezza)
  - [Autenticazione](#autenticazione)
  - [Cifratura dei dati](#cifratura-dei-dati)
  - [Esportazione CSV](#esportazione-csv)
- [Integrazioni esterne](#integrazioni-esterne)
- [Installazione e aggiornamento](#installazione-e-aggiornamento)
- [Punti di estensione](#punti-di-estensione)
- [Debito tecnico noto](#debito-tecnico-noto)

---

## Visione d'insieme

Monolito modulare Symfony 6.4. Un solo processo PHP dietro Apache, un solo database MySQL.
Nessun servizio esterno è obbligatorio per far funzionare l'applicazione: Google Workspace,
Moodle, SPID e Telegram sono integrazioni opzionali, disattivabili da configurazione.

Numeri che danno la dimensione del progetto:

| Metrica | Valore |
|---|---|
| Entità Doctrine mappate | 63 |
| Tabelle nel database | 51 applicative + `gs_messenger_messages` |
| Controller | 33 |
| Rotte applicative | 322 (337 con quelle di framework e profiler in dev) |
| Repository | 64 |
| Template Twig | 421 |
| Classi in `src/Util` | 21, ~26.000 righe |
| Feature Behat | 63 |
| Test PHPUnit | 95 classi |

Il numero di tabelle è molto inferiore al numero di entità perché quasi tutte le entità di
secondo livello condividono la tabella della classe base (vedi [eredità](#eredità)).

---

## Il dominio

### Utenti e ruoli

`App\Entity\Utente` è la radice di tutta la gerarchia utenti. È una `UserInterface` di Symfony
e mappa la tabella `gs_utente` con **single table inheritance**: una sola tabella, una colonna
discriminante `ruolo`.

```
Utente (gs_utente, ruolo='UTE')
├── Amministratore  ruolo='AMM'  codice ruolo M
├── Preside         ruolo='PRE'  codice ruolo P
├── Staff           ruolo='STA'  codice ruolo S
├── Docente         ruolo='DOC'  codice ruolo D
├── Ata             ruolo='ATA'  codice ruolo T
├── Alunno          ruolo='ALU'  codice ruolo A
└── Genitore        ruolo='GEN'  codice ruolo G
```

Ci sono **due sistemi di codifica dei ruoli**, in parallelo, ed è la cosa più importante da
capire del progetto:

1. **I ruoli Symfony** (`Utente::getRoles()`), usati da `#[IsGranted]` e da `switch_user`:
   `ROLE_UTENTE` (in sempre), `ROLE_AMMINISTRATORE`, `ROLE_PRESIDE`, `ROLE_STAFF`, `ROLE_DOCENTE`,
   `ROLE_ATA`, `ROLE_ALUNNO`, `ROLE_GENITORE`.

   Nota la gerarchia indiretta: `Preside` riceve `ROLE_PRESIDE`, `ROLE_STAFF`, `ROLE_DOCENTE`,
   `ROLE_UTENTE`; `Staff` riceve `ROLE_STAFF`, `ROLE_DOCENTE`, `ROLE_UTENTE`. Chi è preside può
   quindi passare i controlli dei ruoli inferiori senza gestire codici speciali.

2. **I codici ruolo a una lettera** (`Utente::getCodiceRuolo()`), usati per la visibilità dei
   menu e i controlli puntuali: `N` anonimo, `U` utente generico, `A` alunno, `G` genitore,
   `D` docente, `S` staff, `P` preside, `T` ATA, `M` amministratore.

   Questi codici si confrontano con `controllaRuolo('ADG')`, che verifica se il codice dell'utente
   è contenuto nella stringa. È una `str_contains`, non un confronto di uguaglianza: per questo
   le stringhe di confronto sono costruite con attenzione.

**Le funzioni** (`Utente::getCodiceFunzioni()`) sono il secondo livello: capability specifiche del
ruolo. Ogni utente ha sempre `N` (nessuna). Le altre dipendono da attributi specifici:

| Funzione | Chi la possiede | Come è calcolata |
|---|---|---|
| `S` | responsabile sicurezza (RSPP) | flag `rspp` su `Utente` |
| `B` | responsabile BES | flag `responsabileBes` su `Docente` |
| `E` | segreteria | flag `segreteria` su `Ata` |
| `C` | rappresentante di classe | array `rappresentante` |
| `I` | rappresentante di istituto | array `rappresentante` |
| `P` | consulta provinciale | solo alunni e genitori |
| `R` | RSU | solo docenti |
| `M` | maggiorenne | calcolato a runtime per gli alunni da `dataNascita` |

`controllaRuoloFunzione('DS,AN')` confronta coppie ruolo+funzione separate da virgola: primo
carattere il ruolo, secondo la funzione. È il formato usato nella colonna `funzione` di
`gs_menu_opzione`.

Le classi specializzate restringono anche i valori ammessi di `rappresentante`: `Alunno` e
`Genitore` filtrano su `I/C/P` (niente RSU), `Docente` su `I/R` (niente rappresentanti di classe),
`Utente::setRappresentante()` come default tiene `I/C/P/R`. Se aggiungi un ruolo, il override
di `setRappresentante` va aggiornato.

#### Profili multipli

Una stessa persona fisica può avere più profili (docente + staff, alunno + rappresentante).
`AuthenticatorTrait::controllaProfili()` cerca i profili attivi con nome, cognome e codice
fiscale; se sono più di uno e c'è un alunno, scarta la combinazione (un alunno non può essere
anche docente). I profili trovati finiscono in sessione sotto `/APP/UTENTE/lista_profili` e
l'utente viene reindirizzato a `/login/profilo` per scegliere.

#### Impersonazione

`SistemaController::alias()` usa `switch_user` (parametro `_alias` in
`config/packages/security.yaml`, consentito solo a `ROLE_AMMINISTRATORE`). I dati dell'utente
reale sono salvati in sessione con prefisso `/APP/UTENTE/*_reale` e ripristinati da
`aliasExit()`. Ogni entrata e uscita dall'alias viene loggata.

### Organizzazione scolastica

```
Istituto  (una sola istanza, dati identificativi e firme)
  └── Sede (più sedi fisiche)
        └── Orario (la scansione oraria della sede)
              └── ScansioneOraria (un'ora della scansione)
  └── Corso (percorso di studi)
        └── Classe (anno + corso + sede)
              ├── Docente coordinatore
              ├── Docente segretario
              ├── Alunno[]
              └── Cattedra (materia insegnata in quella classe dal docente)
```

`Classe` ha due riferimenti opzionali a `Docente`: il coordinatore e il segretario. Sono ruoli
distinti dal `ruolo` dell'utente: un docente è coordinatore di *quelle* classi, non per ruolo.

`Materia` è un elenco piatto con `nome`, `nomeBreve` e `ordinamento`. La cattedra è l'incrocio
`Docente × Materia × Classe` (con due campi opzionali: `alunno` per il sostegno individualizzato e
`docenteSecondario` per il complementare).

`Festivita` ha un riferimento opzionale a `Sede`: un festivo può valere per tutte le sedi o solo
per una.

### Lezioni, presenze e assenze

Il nucleo del registro di classe:

- **Lezione** — un argomento svolto in una classe, con `Materia`, data/ora e `ModuloFormativo`
  (PCTO) opzionale. Le firme dei docenti (`Firma`) si agganciano a `Lezione`.
- **AssenzaLezione** — la riga di appello: alunno × lezione. Da questo derivano i dati
  giornalieri.
- **Assenza**, **Entrata**, **Uscita** — le tre entità "giornaliere", ciascuna con
  `Alunno`, `Docente` (autore della rilevazione), `DocenteJustificazione` e `Genitore`
  (chi ha comunicato). Condividono la stessa forma: sono le proiezioni giornaliere delle
  `AssenzaLezione`.
- **Nota** — annotazioni sul registro. Molti-a-molti con `Alunno` (tabella `gs_nota_alunno`), con
  un `Avviso` come destinatario della bacheca.
- **Annotazione** — la firma di presa visione, con `Avviso`, `Classe`, `Docente`.

### Valutazioni e scrutini

```
Valutazione  (docente, alunno, lezione, materia) — voto durante l'anno
Scrutinio    (classe + periodo) — il consesso
  ├── Esito        (scrutinio, alunno) — giudizio sintetico
  └── VotoScrutinio (scrutinio, alunno, materia) — voto per materia
        └── PropostaVoto (alunno, classe, materia, docente) — la proposta del docente
StoricoEsito / StoricoVoto — le proposte non confermate dallo scrutinio
```

`StoricoEsito` e `StoricoVoto` conservano le proposte dei docenti che non sono state confermate,
per poter essere mostrate allo studente e al genitore in chiaro. È una scelta deliberata di
trasparenza, non un errore.

`DefinizioneConsiglio` e `DefinizioneScrutinio` (single table, `gs_definizione_consiglio`)
descrivono la configurazione degli scrutini per periodo.

### Comunicazioni

La **rifinitura dell'1.6.1** ha sostituito tre tabelle separate (`gs_avviso`, `gs_circolare`,
`gs_documento`) con una sola, `gs_comunicazione`. È il cambiamento architetturale più recente e
spiega perché `Documento` ed `Avviso` non hanno una tabella propria.

```
Comunicazione (gs_comunicazione, categoria ∈ {D, C, A})
├── Documento   categoria='D'  — piano di lavoro, programma, relazione, documento del 15 maggio
├── Circolare   categoria='C'  — circolare, destinatari obbligatori
└── Avviso      categoria='A'  — avviso generico, ingresso, uscita, attività, personale
```

Le destinazioni sono entità a sé, tutte con la stessa forma (`comunicazione_id` + destinatario):

| Entità | Tabella | Destinatario |
|---|---|---|
| `ComunicazioneClasse` | `gs_comunicazione_classe` | `Classe` |
| `ComunicazioneUtente` | `gs_comunicazione_utente` | `Utente` |
| — | `gs_comunicazione_sede` | `Sede` (join table) |

Gli allegati sono in `Allegato` con `orphanRemoval: true`.

**Il reflusso di `Documento` e `Avviso`**: il passaggio a `Comunicazione` come classe base ha
trasformato le due entità storiche in sottoclassi che aggiungono campi specifici. La
discriminante è la colonna `categoria`. Quando si tocca questo codice bisogna tenere conto che
i template e i controller referenziano `Avviso::class` pur scrivendo e leggendo da
`gs_comunicazione`.

### Documenti

Oltre ai `Documento` (che sono comunicazioni), esiste un insieme di entità per i documenti con
visibilità ristretta, tutti con `Alunno` come destinatario: `Firma` (con `FirmaSostegno` per le
firme del docente di sostegno), `OsservazioneClasse` (con `OsservazioneAlunno`), `DocumentoBES`.
I PDF BES sono cifrati: le note BES dell'alunno sono un campo `EncryptedStringType`.

### Configurazione e menu

`Configurazione` è una tabella chiave-valore con tre categorie, letta in sessione all'accesso:

| Categoria | Contiene |
|---|---|
| `SISTEMA` | versione, banner, manutenzione, dominio predefinito, token del comando di notifica, Telegram |
| `SCUOLA` | anno scolastico, periodi, soglie di voto, notifiche, gestione uscite, festività |
| `ACCESSO` | SPID, identity provider, tipo di OTP |

`Istituto` e `Sede` tengono i dati identificativi (intestazione, firme, PEC, contatti), separati
dalla configurazione.

**Menu e breadcrumb sono dati, non codice.** `Menu` e `MenuOpzione` descrivono l'albero di
navigazione; ogni opzione ha `ruolo` (stringa di codici a una lettera) e `funzione` (stringa di
coppie ruolo+funzione). `ConfigLoader::caricaMenu()` chiede al repository il menu filtrato per
l'utente corrente e lo mette in sessione. `MenuOpzione::url` contiene il **nome della rotta**, non
l'URL: è il template a fare il `path()`.

Conseguenza pratica: **aggiungere una voce di menu è una modifica al database**, non al codice.
Le fixture `MenuFixtures.yml` e `MenuOpzioneFixtures.yml` sono la fonte di verità per
un'installazione nuova.

### Eredità — riepilogo

Sei gerarchie single-table nel progetto. Conoscerle evita errori puzzling:

| Radice | Tabella | Discriminante | Sottoclassi |
|---|---|---|---|
| `Utente` | `gs_utente` | `ruolo` (3 char) | 7 ruoli applicativi |
| `Comunicazione` | `gs_comunicazione` | `categoria` (1 char) | `Documento`, `Circolare`, `Avviso` |
| `DefinizioneRichiesta` | `gs_definizione_richiesta` | — | `DefinizioneConsultazione`, `DefinizioneAutorizzazione` |
| `DefinizioneConsiglio` | `gs_definizione_consiglio` | — | `DefinizioneScrutinio` |
| `Firma` | `gs_firma` | — | `FirmaSostegno` |
| `OsservazioneClasse` | `gs_osservazione` | — | `OsservazioneAlunno` |

Le ultime tre non hanno `#[ORM\Table]` proprio: senza attributo, Doctrine eredita la tabella della
classe base. È il motivo per cui `gs_firma` contiene anche le firme di sostegno.

---

## Strati applicativi

Il codice è diviso per directory, non per bounded context. I confini funzionali sono impliciti.

```
Controller  (src/Controller)
    ↓ orchestrano
Util        (src/Util)          ← la logica di dominio vive qui
    ↓ usa
Repository   (src/Repository)   ← query complesse e query per il menu
    ↓ su
Entity       (src/Entity)       ← dati e invarianti locali
```

**I controller sono sottili nel corpo ma grandi nel file.** `ScrutinioController` (2.248 righe),
`SistemaController` (1.904), `RegistroController` (1.785). Il grosso del lavoro è delegato ai
`Util`; quello che resta nei controller è gestione form, redirect e costruzione delle variabili
per il template.

**Gli `Util` sono il cuore del dominio.** I più grandi:

| Classe | Righe | Responsabilità |
|---|---|---|
| `ScrutinioUtil` | 5.364 | calcolo scrutini, medie, proposte, esiti, crediti, certificazioni |
| `RegistroUtil` | 3.692 | registro di classe: appello, firme, note, giustificazioni |
| `ArchiviazioneUtil` | 3.520 | archiviazione annuale di registri e documenti |
| `PagelleUtil` | 3.244 | generazione pagelle e comunicazioni dei risultati |
| `CsvImporter` | 1.958 | import massivo da CSV di alunni, docenti, ATA |
| `ComunicazioniUtil` | 1.910 | gestione comunicazioni, destinatari, allegati |
| `AccountProvisioning` | 1.758 | provisioning su Google Workspace e Moodle |

Non è un layering rigido: gli `Util` si chiamano fra loro e alcuni (`AccountProvisioning`) parlano
direttamente con le API esterne. `SegreteriaUtil`, `StaffUtil` e `GenitoriUtil` seguono una
convenzione di nome diversa: sono le funzioni *disponibili a un ruolo*, iniettate nei controller
per il ruolo che serve.

**I repository sono lo strato delle query.** `BaseRepository` fornisce i metodi comuni. I
repository hanno metodi di dominio, non solo `find*`: `ClasseRepository::listaClassi()`,
`AlunnoRepository::cercaAlunno()`, `MenuRepository::menu($selettore, $utente)`. I controller
passano dati ai template tramite questi metodi.

**Le dipendenze dei controller sono argomenti di azione.** Non c'è il costruttore: `src/Controller`
è registrato con il tag `controller.service_arguments`, quindi ogni servizio necessario è un
parametro della route. `BaseController` fornisce `$this->em` e `$this->reqstack`.

---

## Configurazione in sessione

`ConfigLoader::carica()` è il punto in cui il database diventa disponibilità per i template. Al
login (e a ogni `?reload=yes`) legge `Configurazione`, `Istituto`, `Sede`, i menu filtrati per
l'utente e li versa in sessione con chiavi prefissate:

```
/CONFIG/SISTEMA/…      /CONFIG/SCUOLA/…      /CONFIG/ACCESSO/…    da gs_configurazione
/CONFIG/ISTITUTO/…     da Istituto + Sede
/CONFIG/MENU/…         menu filtrati per l'utente
/APP/APP/tema          'admin' per l'amministratore, '' altrimenti
/APP/UTENTE/…          dati della sessione dell'utente
/APP/<RUOLO>/…         stato per ruolo (es. /APP/DOCENTE/coordinatore)
/APP/ROUTE/<rotta>/…   filtri e paginazione delle liste
```

Nei template si legge con `app.session.get('/CONFIG/SCUOLA/anno_inizio')`.

**Conseguenze da tenere a mente:**

- I template **non** interrogano il database per la configurazione. Passare un dato dalla
  configurazione al template significa leggere la sessione.
- Le chiavi `/APP/ROUTE/…` sono il meccanismo di **persistenza dei filtri e della paginazione**
  delle liste tra una richiesta e l'altra. Quando aggiungi un filtro a una lista, devi decidere se
  va in sessione (se sopravvive alla navigazione) o no.
- Il cache della configurazione è invalidato ricaricando: `ConfigLoader::carica()` rimuove tutte
  le chiavi `/CONFIG/` e `/APP/` (tranne `/APP/UTENTE/`) prima di ricaricare.

---

## Rendering

Tutte le pagine passano da `BaseController`, che ha due metodi di output.

### `renderHtml($categoria, $azione, $dati, $info, $form)`

Risolve il template così:

```
{tema/}{categoria}/{azione}.html.twig
```

Il prefisso `tema` è `admin/` **solo** per l'utente amministratore. Quindi la stessa azione ha
due varianti di template:

```
renderHtml('scuola', 'consultazioni')
  → templates/admin/scuola/consultazioni.html.twig   (amministratore)
  → templates/scuola/consultazioni.html.twig         (chiunque altro)
```

In pratica `templates/admin/` contiene le pagine di configurazione, raggiungibili solo da
amministratore, mentre le pagine operative stanno nelle cartelle della categoria. La scelta
del tema è in `ConfigLoader::caricaTema()`.

Il metodo passa al template quattro variabili fisse:

| Variabile | Contenuto |
|---|---|
| `pagina_titolo` | chiave di traduzione `page.{categoria}.{azione_principale}` |
| `titolo` | chiave di traduzione `title.{categoria}.{azione}` |
| `breadcrumb` | da `MenuOpzioneRepository::breadcrumb()`, null se non c'è il nuovo tema |
| `dati` | righe della tabella |
| `info` | informazioni singole (testata, riepiloghi) |
| `form` | vista del form e messaggi |

I nomi delle rotte e quelli dei template sono allineati per costruzione: la route `avvisi_gestione`
renderizza `avvisi/gestione.html.twig`.

### `renderCsv($categoria, $azione, $nomefile, $dati, $info)`

Come sopra ma su `.csv.twig`, con l'anteposizione del BOM UTF-8 e
`Content-Type: text/csv; charset=UTF-8`. Vedi [esportazione CSV](#esportazione-csv).

### Estensioni Twig

| Estensione | Funzione | A cosa serve |
|---|---|---|
| `CsvExtension` | filtro `csv` | quoting CSV e neutralizzazione formule |
| `FiledateExtension` | funzione `filedate()` | timestamp del file per il cache-busting degli asset |
| `Image64Extension` | funzione `image64()` | inline di immagini (PDF, email) |
| `InstanceofExtension` | test `instanceof` | controlli di tipo nei template |

### Funzioni DQL

`src/DQL/` implementa funzioni SQL mancanti in MySQL tramite nodi Doctrine, registrate in
`config/packages/doctrine.yaml`:

- numeriche: `day()`, `month()`, `weekday()`, `year()`
- stringa: `date_format()`, `find_in_set()`, `if()`, `instr()`, `replace()`

Sono il sostituto delle funzioni che mancano in MySQL e servono a costruire query di ricerca e
aggregazione che altrimenti richiederebbero SQL raw.

### Personalizzazione

`PERSONAL/templates/` e `PERSONAL/data/` sono registrati come path Twig **prima** di `templates/`
e `PERSONAL/data`, nel namespace `data`. `PERSONAL/translations/` è il `default_path` del
translator, quindi ha precedenza su `translations/`.

Risultato: un istituto può sovrascrivere un template o unMessaggio senza toccare il repository.
Entrambe le directory sono vuote nel repository (solo `.gitkeep`): sono un punto di estensione
previsto, non una feature attiva.

---

## Notifiche

Le notifiche hanno una catena a due passi, con code separate e ritardi.

```
Avviso / Circolare creato o modificato
  ↓  (dispatch con DelayStamp)
[avviso] / [circolare]        elaborano il contenuto, calcolano i destinatari
  ↓  (dispatch)
[notifica]                   invia a ciascun utente
  ↓
email (symfony/mailer) o Telegram (TelegramManager)
```

**Le quattro code** sono su `doctrine://default`, tutte sulla stessa tabella
`gs_messenger_messages` con un `queue_name` diverso:

| Coda | Retry | Delay | Contenuto |
|---|---|---|---|
| `avviso` | 3 | 10s × 3 | `AvvisoMessage` |
| `circolare` | 3 | 10s × 3 | `CircolareMessage` |
| `evento` | 3 | 10s × 4 | `EventoMessage` |
| `notifica` | 3 | 600s × 4 | `NotificaMessage` |
| `errore` | — | — | transport di fallback dei messaggi falliti |

**Il ritardo di 30 minuti sugli avvisi** è una scelta di progetto, non un errore: un avviso
creato o modificato non notifica subito. `NotificaMessageHandler::update()` rimuove il messaggio
in attesa e lo ripianifica se l'avviso viene modificato di nuovo entro la finestra
(`if (!$edit || !NotificaMessageHandler::update(...))`).

**Le circolari hanno un orario di pubblicazione.** `CircolariController::publish()` calcola
l'orario da `/CONFIG/SCUOLA/notifica_circolari`; se è già passato, la circolare viene
programmata per il giorno seguente.

**Il tag** è la chiave di deduplicazione: ogni messaggio porta un `tag` derivato dall'oggetto
(`NotificaMessageHandler::delete($em, $tag)` rimuove dalla coda, `update()` ripianifica).

**Il consumo è manuale e schedulato.** Non c'è un worker: `CommandController::notify()` espone
`GET /command/notify/{token}/{time}` che esegue `messenger:consume` con `--time-limit`,
protetto dal parametro `comando_token` confrontato con `hash_equals`. Va messo in cron.

**Il risparmio di tempo si ottiene con il batching.** Le circolari possono avere molti
destinatari: `CircolareMessageHandler` implementa `BatchHandlerInterface` e le azioni di
destinatari sono raggruppate con `FlushBatchHandlersStamp(true)`, così il bus non scrive una
riga per destinatario.

`EventoMessageHandler` è uno stub: il corpo di `__invoke` è un commento. La struttura è pronta,
l'implementazione no.

---

## Log e audit

Ci sono due log distinti, spesso confusi.

### Il log su file (Monolog)

Canali per destinazione, tutti in `var/log/` con rotazione:

```
app_<env>.log        il generale
command_<env>.log    i comandi console
messenger_<env>.log  il bus messaggi
deprecation_<env>.log (solo in dev)
```

Il livello è controllato da `LOG_LEVEL`. `LogProcessor` aggiunge il contesto della richiesta a
ogni record.

### Il log su database (`gs_log`)

È il **registro delle azioni degli utenti** ed è la parte importante dal punto di vista della
responsabilità. Funziona così:

1. `LogListener` è un listener Doctrine su `onFlush` e `postFlush` (attivo solo in `prod` e
   `dev`, via `#[When(env: 'prod')] #[When(env: 'dev')]`). Intercetta inserimenti, modifiche e
   cancellazioni, esclude le entità rumorose (`AssenzaLezione`, `Comunicazione*`, `Log` stesso,
   `Provisioning`, `AutenticazioneDispositivo`) e i campi `id/creato/modificato`, e accoda
   i log in memoria.
2. `LogSubscriber` su `kernel.terminate` scrive la coda su database con i dati della richiesta:
   utente, username, ruolo, alias in uso, IP, origine.
3. `LogHandler` (`logAzione($categoria, $azione, $dati = [])`) è l'API per i log espliciti
   delle azioni non copercettibili dall'intercettazione (login, alias, export).

Il log è registrato a `kernel.terminate`, non durante la richiesta: le operazioni di scrittura non
allungano il tempo di risposta ma non sono visibili finché la richiesta non è finita.

**Attenzione**: in ambiente `test` il listener è disattivato. Se aggiungi un test che verifica il
log, il listener non ci sarà.

---

## Sicurezza

### Autenticazione

Un solo firewall (`main`), `lazy: true`, con sei autenticatori. Ognuno dichiara le route su cui
agisce in `supports()`, quindi sono mutuamente esclusivi:

| Autenticatoro | Route | Metodo |
|---|---|---|
| `FormAuthenticator` | `login_form`, `login_utente` | POST |
| `MimSpidAuthenticator` | `login_mimspid_check` | GET |
| `GSuiteAuthenticator` | `login_gsuite_check` | GET |
| `TokenAuthenticator` | `login_token` | POST |
| `TokenConnectAuthenticator` | `login_connect` | GET |
| `AuthConnectAuthenticator` | `api_authConnect` | GET |

`AuthenticationEntryPoint` gestisce il redirect alla pagina di login quando serve.

**Profili e manutenzione** sono controllati in `AuthenticatorTrait`:

- `controllaManutenzione()` — se la finestra di manutenzione è attiva, blocca tutti tranne
  l'amministratore
- `controllaProfili()` — risolve i profili multipli come descritto sopra

**Gli hash deboli sono intenzionali.** `TokenAuthenticator` e `TokenConnectAuthenticator` usano
`sha1`/`md5` per il binding del token OTP all'IP. Non sono vulnerabilità: sono hashing di
identificatori, non di segreti, e i file portano un commento che lo spiega. **Non "correggere"
queste occorrenze** senza aver letto il commento e senza aver verificato cosa ne segue.

**La modalità manutenzione** è una finestra temporale in configurazione, non un flag booleano:
`manutenzione_inizio` e `manutenzione_fine`. Il check è in `controllaManutenzione()`.

**Le API per l'app mobile** hanno un proprio ciclo di vita, in `ApiController`:
`authRegister` → `authRequest` → `authValidate` → `authConnect`, con `AutenticazioneDispositivo`
come entità di stato. L'header `X-Giuaschool-Token` autentica le richieste. `/api/info/docente`
verifica anche che l'IP chiamante corrisponda a quello configurato per il servizio.

### Cifratura dei dati

`App\Security\Encryptor` fa cifratura simmetrica **AES-256-GCM** con IV casuale e authentication
tag. Il formato memorizzato è:

```
__GS-ENC-v1__ base64(IV + TAG + ciphertext)
```

Il prefisso rende la cifratura **non distruttiva sulle righe esistenti**: `decrypt()` restituisce
il valore invariato se non trova il prefisso, quindi si può fare la migrazione a colonne cifrate
senza riscrivere i dati già presenti.

L'integrazione con Doctrine è `App\Doctrine\EncryptedStringType`, un tipo `Type` registrato in
`Kernel::boot()` e dichiarato nelle entità con:

```php
#[ORM\Column(name: 'note_bes', type: EncryptedStringType::NAME, nullable: true)]
private ?string $noteBes = '';
```

**Attenzione alla chiave**: la chiave è `APP_SECRET`. Ruotare `APP_SECRET` in un database
popolato rende illeggibili tutti i dati cifrati, senza lasciare traccia nel DB. Se serve una
rotazione, va fatta con una procedura che rilegga e riscriva ogni campo.

Cifrati oggi: note BES degli alunni, e i documenti BES cifrati come PDF.

### Esportazione CSV

I tre template `.csv.twig` sono `lezioni/voti_quadro`, `richieste/modulo_lista` e
`richieste/modulo_evacuazione`.

**Regola: mai `{% autoescape false %}` in un `.csv.twig`.** Il filtro `|csv` fa il quoting
RFC 4180 e neutralizza la CSV injection (i valori che iniziano con `=`, `+`, `-`, `@`, TAB o CR
vengono prefissati con un apice singolo, così Excel li tratta come testo). Le virgolette sono
raddoppiate e i CR/LF sostituiti con uno spazio per non spezzare le righe.

```twig
{{ (cv[0] == 'bool' ? (r.valori[c] ? 'SI' : 'NO') : r.valori[c])|csv }}
```

Il BOM UTF-8 e l'header `charset=UTF-8` li aggiunge `renderCsv()`: senza, Excel su Windows legge
il file con la codepage locale e gli accenti dei nomi diventano illeggibili.

### Altro

- **CSRF** abilitato globalmente (`framework.csrf_protection.enabled: true`)
- **Sessione**: cookie `SameSite=lax`, `HttpOnly`, `Secure=auto`, `sid_length: 64`,
  salvataggio su filesystem in `var/sessions/<env>`
- **Password**: `algorithm: auto`; in test con costi ridotti (`cost: 4`) per non rallentare
- **Validazione email**: modalità `html5`
- **`not_compromised_password`** è disabilitato — decisione deliberata, l'istituto non ha
  accesso al servizio di Have I Been Pwned
- **Rotte**: `strict_requirements: true` in dev/test, `null` in prod (le regex dei parametri non
  sono più applicate per non scaricare il router in produzione)

---

## Integrazioni esterne

Tutte opzionali, tutte in `AccountProvisioning` (1.758 righe) tranne SPID e Telegram.

### Google Workspace

`google/apiclient` + Admin Directory API. Crea e modifica utenti, assegna gruppi, gestisce
cattedre. Le credenziali arrivano da
`config/secrets/registro-elettronico-utenti-gsuite.json`, escluso da git e da `.dockerignore`.

**Attenzione**: la creazione utente usa `sha1($password)` come funzione di hash. Non è una
scelta libera dell'applicazione: la Google Directory API richiede SHA-1 per questo scopo. Il
commento nel codice lo dice. Non va "corretto" senza una verifica a monte.

**Stato della verifica**: il fatto che il file di credenziali non sia nel repository **non dimostra**
che l'integrazione sia abbandonata — i file di credenziali sono esclusi da git per definizione.
Va verificato nell'ambiente dove gira `app:provisioning:esegue`.

**Bug noto**: `inizializza()` ritorna subito se `inizializzaGsuite()` fallisce, **impedendo
anche l'inizializzazione di Moodle**. In un istituto che usa Moodle senza credenziali Google, il
provisioning è completamente rotto. Il fix richiesto è distinguere "non configurato" (si prosegue
con Moodle) da "configurato ma rotto" (errore), con i quattro test elencati in `PIANO_SICUREZZA.md`
Fase 3-bis. **Non risolvere con "ignora l'errore"**: nasconderebbe anche i guasti reali.

### Moodle

Chiamato dalla stessa classe, dopo Google. Condivide la struttura dei comandi.

### SPID / CIE

OAuth2 generico contro il gateway MIM. `MimSpidProvider` implementa il provider OAuth2,
`MimSpidValidator` valida l'ID token contro il JWKS (con cache su filesystem, TTL 1 ora,
`mimspid.jwks`). La configurazione è in `SistemaController::spid()` e i parametri
`/CONFIG/ACCESSO/spid`, `id_provider`, `id_provider_tipo`.

### Telegram

`TelegramManager` è un client HTTP diretto del Bot API (nessuna libreria). Token e chat id
sono in configurazione. Ogni utente sceglie il canale di notifica (email o Telegram) e quali
tipi ricevere; i dati sono nell'array `notifica` dell'utente.

### Il pattern del provisioning

Il provisioning **non** chiama mai le API esterne durante una richiesta HTTP. Le azioni sono
accodate come righe in `gs_provisioning`, create da ~30 punti nell'applicazione (controller,
`CsvImporter`, i listener `UtenteCreatoListener` e `UtenteModificatoListener`). Poi un comando
le esegue:

```bash
php bin/console app:provisioning:esegue
```

Vantaggi: la richiesta HTTP non dipende dalla disponibilità di Google, gli errori sono
riportati e ritentabili, e il comando può girare da cron. In caso di errore di
inizializzazione i comandi in attesa vengono ripristinati (`ripristinaComandi`).

---

## Installazione e aggiornamento

Fuori dal framework: script standalone in `public/install/` con logica in `src/Install/`.

| Script | Passi |
|---|---|
| `public/install/app.php` | `requirements`, `database`, `schema`, `admin`, `clean`, `end` |
| `public/install/update.php` | `unzip`, `fileUpdate`, `requirementsUpdate`, `schemaUpdate`, `envUpdate`, `procedureUpdate`, `cleanUpdate`, `endUpdate` |

I file `src/Install/update-vX.Y.Z` (e `update-vX.Y.Z-build` per le build) dichiarano
`fileCopy`, `fileDelete`, `sqlCommand`, `envDelete`, `procedure`. La sezione `procedure` è
eseguita con `eval()` e contiene di norma chiamate al migrator; `update-v1.6.1` è l'eccezione
con SQL e loop espliciti.

`DataMigrator` contiene i metodi di migrazione dati che il SQL da solo non esprime (per esempio
la migrazione `Documento` → `Comunicazione` dell'1.6.1, che doveva spostare dati tra tabelle con
strutture diverse).

Lo stato corrente è in `.gs-updating` (token, versione, build) e nei parametri `versione` e
`versione_build` del database. Il confronto con la nuova versione è fatto da
`SistemaController::aggiorna()`, che poi delega a `update.php`.

La procedura di passaggio all'anno scolastico seguente è separata e sta in
`SistemaController::nuovo()`.

---

## Punti di estensione

| Vuoi… | Aggiungi |
|---|---|
| una pagina | metodo in un controller con `#[Route]` + `#[IsGranted]` + `renderHtml` |
| un ruolo o una funzione | sottoclasse di `Utente`, override di `getCodiceRuolo`/`getCodiceFunzione`/`getRoles`, nuovo codice in `DiscriminatorMap` |
| una voce di menu | riga in `gs_menu_opzione` (fixture `MenuOpzioneFixtures.yml`), non codice |
| un tipo di form | classe in `src/Form`, `createForm(TipoForm::class, null, ['form_mode' => …])` |
| un filtro o una funzione Twig | `src/Twig/` |
| una funzione DQL | `src/DQL/` + `config/packages/doctrine.yaml` |
| una colonna cifrata | `EncryptedStringType::NAME` |
| una coda di messaggi | `src/Message/` + `src/MessageHandler/` + `routing` in `messenger.yaml` |
| una notifica esterna | nuovo `MessageHandler` che dispaccia `NotificaMessage` |
| un'azione su sistemi esterni | nuova `funzione` in `gs_provisioning` + `case` in `ProvisioningCommand::esegueProvisioning()` |
| una personalizzazione per istituto | file in `PERSONAL/templates/` o `PERSONAL/translations/` |
| un test funzionale | `.feature` in `tests/features/` + fixture `_xxxFixtures.yml` |

**Aggiungere un ruolo richiede attenzione**: la `DiscriminatorMap` di `Utente` deve includere la
nuova classe, i `ROLE_*` devono essere coerenti con la gerarchia (un ruolo nuovo che deve vedere
le pagine del ruolo inferiore deve includerne il `ROLE`), e `getCodiceRuolo` va usato nei
confronti con `controllaRuolo`. Le stringhe di confronto dei menu sono costruite a mano: un
codice nuovo va aggiunto a ogni elenco pertinente.

---

## Debito tecnico noto

Punti verificati nel codice, elencati per priorità. I dettagli e i piani di intervento sono in
`PIANO_SICUREZZA.md` e `PIANO_COMMIT.md` (file locali, non versionati).

**Correzione funzionale**

- `AccountProvisioning::inizializza()` blocca l'inizializzazione di Moodle quando Google Workspace
  non è configurato. Priorità alta, richiede test.

**Sicurezza**

- `eval()` in `Updater.php` sulla sezione `procedure` dei file `update-v*`. La soluzione
  indicata è un allowlist dei nomi di metodo del migrator, con gestione separata dei file
  "legacy" (`update-v1.6.1` e precedenti) che non seguono il pattern.
- `AccountProvisioning::inizializzaGsuite()` usa `sha1($password)` per un requisito della Google
  Directory API. Il commento nel codice lo dichiara; va rimosso finché lo stato dell'integrazione
  non è verificato nell'ambiente di esecuzione.

**Codice incompleto**

- `EventoMessageHandler::__invoke()` è uno stub: solo commenti.
- `ApiController::device()` è marcato `// @TODO: DA RIMUOVERE`.
- `Utente` ha tre campi marcati `@TODO RIMUOVERE` (`prelogin`, `preloginCreato`, `dispositivo`).

**Configurazione**

- `rector.php` ha tutte le regole commentate: è uno scheletro, non una configurazione attiva.
- twig-cs-fixer è installato senza file di configurazione.
- Psalm a `errorLevel 5` con `findUnusedCode` non è pulito e non gira in CI. Va usato come
  strumento di osservazione del debito, non come gate.

**Test**

- I test funzionali non coprono l'installazione e l'aggiornamento, salvo tre `.feature` dedicate
  (`test-update-1/2/3.feature`) eseguite solo dal job `test-update` su `master`.