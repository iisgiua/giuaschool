# giuaschool ![GitHub release (latest by date)](https://img.shields.io/github/v/release/iisgiua/giuaschool)

*Il Registro Elettronico open source dell'Istituto di Istruzione Superiore "Michele Giua"*

[![Test and deploy](https://github.com/iisgiua/giuaschool/actions/workflows/test-deploy.yml/badge.svg?branch=master)](https://github.com/iisgiua/giuaschool/actions/workflows/test-deploy.yml)
[![Codacy Grade](https://app.codacy.com/project/badge/Grade/fe9a2c70a8d84667a114fff45e942260)](https://www.codacy.com/gh/iisgiua/giuaschool/dashboard?utm_source=github.com&utm_medium=referral&utm_content=iisgiua/giuaschool&utm_campaign=Badge_Grade)
[![Codacy Coverage](https://app.codacy.com/project/badge/Coverage/fe9a2c70a8d84667a114fff45e942260)](https://www.codacy.com/gh/iisgiua/giuaschool/dashboard?utm_source=github.com&utm_medium=referral&utm_content=iisgiua/giuaschool&utm_campaign=Badge_Coverage)

---

**giua@school** è un gestionale scolastico completo: gestione dell'organizzazione della
scuola, dell'orario, delle lezioni, delle assenze, delle valutazioni, degli scrutini, delle
comunicazioni verso famiglie e personale, dei documenti di classe e dei moduli. È multi-sede,
multi-anno-scolastico e supporta più profili per la stessa persona fisica (un docente può essere
anche staff, un alunno può essere anche rappresentante di classe).

Questo README è rivolto a chi sviluppa sul progetto. La documentazione **per gli utenti finali**
(manuali, guide) è in un repository separato:
**[iisgiua.github.io/giuaschool-docs](https://iisgiua.github.io/giuaschool-docs/)**.

Per l'architettura, il modello di dominio e i flussi interni vedi **[docs/ARCHITETTURA.md](docs/ARCHITETTURA.md)**.

---

## Indice

- [Stack tecnologico](#stack-tecnologico)
- [Requisiti](#requisiti)
- [Setup locale](#setup-locale)
- [Variabili d'ambiente](#variabili-dambiente)
- [Comandi frequenti](#comandi-frequenti)
- [Test](#test)
- [Struttura del progetto](#struttura-del-progetto)
- [Convenzioni di codice](#convenzioni-di-codice)
- [Installazione e aggiornamento](#installazione-e-aggiornamento)
- [Deployment](#deployment)
- [Documentazione e supporto](#documentazione-e-supporto)

---

## Stack tecnologico

| Componente | Scelta | Versione |
|---|---|---|
| Linguaggio | PHP | >= 8.2 |
| Framework | Symfony | 6.4.* |
| ORM | Doctrine ORM | ^3.3 |
| Database | MySQL / MariaDB (InnoDB, `utf8` / `utf8_unicode_ci`) | — |
| Template engine | Twig | ^3.0 |
| Code styling | twig-cs-fixer | ^4.1 |
| Analisi statica | Psalm (errorLevel 5) | ^6.19 |
| Refactoring automatico | Rector | ^1.2 |
| Test unitari | PHPUnit | ^11.4 |
| Test funzionali | Behat + Mink + Chrome headless | — |
| Dati di test | Alice / nelmio-alice + Faker | ^3.9 / ^1.15 |
| PDF | TCPDF (via qipsius/tcpdf-bundle) + FPDI | ^6.7.5 |
| Office (docx/xlsx) | PHPWord | ^0.18 |
| Code scanning | SonarQube / Codacy | — |
| Esterni integrati | Google Workspace (Admin SDK), Moodle, SPID/CIE via gateway MIM, Telegram Bot API | — |

Non ci sono micro-servizi: è un monolito modulare Symfony. Non c'è `doctrine/migrations`:
lo schema evolve tramite un meccanismo di aggiornamento proprio (vedi
[Installazione e aggiornamento](#installazione-e-aggiornamento)).

---

## Requisiti

- PHP **8.2** o superiore, con le estensioni `ctype` e `iconv`
- Composer 2
- MySQL 5.7+ o MariaDB 10.3+
- Un server web (Apache con `mod_rewrite`, o `php -S`)
- Solo per i test funzionali: **Google Chrome** (il driver Mink parla con Chrome headless via
  DevTools protocol sulla porta 9222)

Per l'ambiente di test completo servono inoltre MariaDB e Apache come servizi di sistema, perché
gli script di test li avviano con `service <nome> start`.

---

## Setup locale

### 1. Dipendenze

```bash
composer install
```

### 2. Database

```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:update -f      # o --dump-sql per ispezionare
```

> Lo schema non usa migration versionate. `doctrine:schema:update` è il comando previsto per
> gli ambienti di sviluppo e test; in produzione gli aggiornamenti passano dalla procedura
> descritta più avanti.

### 3. Dati iniziali

Il comando `app:alice:load` carica le fixture Alice. Con l'argomento `_giuaschool` carica il
set minimo di un'installazione fresca (configurazione, materie, menu, utente amministratore):

```bash
php bin/console app:alice:load _giuaschool
```

Senza argomento carica il manifest `_entityTestFixtures.yml`, che include l'intero set delle
fixture di test senza passare separatamente ad Alice anche i file già inclusi — utile per un
ambiente di sviluppo con dati di prova, non per la produzione.

Per un ambiente di sviluppo con **tutte le sezioni popolate** c'è il set demo `_demoFixtures.yml`,
che sovrappone ai dati di test i file `Demo*Fixtures.yml`: ogni classe, docente, alunno e genitore
ha almeno 2-3 record per sezione, compresi **voti** (minimo 3 per alunno) e **compiti/verifiche**
dell'agenda (minimo 4 eventi per alunno e genitore):

```bash
php bin/console app:alice:load _demo
```

Il set demo porta l'anno scolastico all'**A.S. 2026/2027** tramite `DemoConfigurazioneFixtures.yml`
e `DemoOrarioFixtures.yml` (sovrascrittura delle chiavi di configurazione e della validità
dell'orario, sempre senza modificare i file condivisi con i test). Poiché le cattedre sono generate
in modo casuale, al termine del caricamento il comando completa automaticamente il dataset con i
dati legati al singolo utente/cattedra (coordinatori e segretari, documenti, lezioni, argomenti,
valutazioni, osservazioni, proposte di voto, scrutini e tabellone degli scrutini svolti con relativi
esiti delle pagelle, comunicazioni per tutti gli utenti, colloqui, presenze, moduli formativi e
rappresentanti di classe/istituto/RSU/consulta). Il completamento è idempotente e può essere
rieseguito da solo con:

```bash
php bin/console app:demo:populate
```

La riga di comando dell'amministratore creata dalla fixture iniziale è `admin`; la password
viene impostata dalla fixture tramite il provider `PersonaProvider` (vedi `tests/CustomProvider.php`).

### 4. Server

```bash
php -S localhost:8000 -t public
```

Il **document root deve essere `public/`**. I file nella radice del progetto non devono essere
servibili via web: `.htaccess` in radice nega esplicitamente l'accesso.

### 5. Cache

```bash
php bin/console cache:clear
```

---

## Variabili d'ambiente

Tutte in `.env` (vedi `config/services.yaml` e i vari `config/packages/*.yaml` per gli usi).

| Variabile | Uso | Obbligatoria |
|---|---|---|
| `APP_ENV` | Ambiente (`dev`, `prod`, `test`) | sì |
| `APP_SECRET` | Segreto applicazione **e chiave di cifratura** dei campi sensibili | sì |
| `DATABASE_URL` | `mysql://utente:password@host:3306/giuaschool` | sì |
| `MAILER_DSN` | Trasporto mail. `php://default` usa `mail()` di PHP | sì |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` per le code su tabella | sì |
| `LOG_LEVEL` | Livello minimo dei log su file | sì |
| `GOOGLE_API_KEY`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_AUTH_CONFIG` | Google API client per il provisioning Workspace | no (solo se si usa Google Workspace) |
| `OAUTH_GOOGLE_CLIENT_ID`, `OAUTH_GOOGLE_CLIENT_SECRET`, `OAUTH_GOOGLE_CLIENT_HD` | Login OAuth2 con Google Workspace | no |
| `MIMSPID_CLIENT_ID`, `MIMSPID_CLIENT_SECRET` | Login SPID/CIE tramite gateway MIM | no |

`APP_SECRET` ha un doppio ruolo: firma la sessione Symfony **e** è la chiave AES usata da
`App\Security\Encryptor` per cifrare i campi marcati `EncryptedStringType`. Cambiarlo in un
database popolato rende illeggibili i dati cifrati. Vedi
[docs/ARCHITETTURA.md](docs/ARCHITETTURA.md#dati-cifrati).

I file di credenziali esterne (es. `config/secrets/registro-elettronico-utenti-gsuite.json`) non
sono versionati: `config/secrets/` contiene solo `.gitkeep` e va popolato al deploy.

---

## Comandi frequenti

```bash
# --- applicazione ---
php bin/console                                        # elenco dei comandi
php bin/console debug:router                           # mappa delle rotte
php bin/console debug:config doctrine                  # configurazione Doctrine effettiva
php bin/console cache:clear

# --- database ---
php bin/console doctrine:schema:validate               # verifica la sincronizzazione
php bin/console doctrine:schema:update --dump-sql      # SQL che verrà applicato
php bin/console doctrine:mapping:info                  # entità mappate
php bin/console dbal:run-sql "SELECT ..."              # query dirette

# --- dati di prova (Alice) ---
php bin/console app:alice:load _giuaschool              # set minimo
php bin/console app:alice:load                         # tutte le fixture
php bin/console app:alice:load Alunno --append         # aggiunge senza cancellare
php bin/console app:alice:load tests/features/_avvisiFixtures.yml
php bin/console app:alice:load _giuaschool --dump dump # esporta SQL + mappa oggetti

# --- provisioning su sistemi esterni ---
php bin/console app:provisioning:esegue

# --- code di notifica (usato dal cron) ---
php bin/console messenger:consume notifica avviso evento circolare --time-limit=3600

# --- qualità del codice ---
vendor/bin/psalm                    # analisi statica (errorLevel 5)
vendor/bin/rector --dry-run         # propose refactoring (config quasi tutto commentato)
vendor/bin/twig-cs-fixer fix       # stile dei template (vedi nota)
```

> **Nota su twig-cs-fixer**: il pacchetto è installato ma non c'è un file di configurazione
> `.twig-cs-fixer.dist.yaml` nel repository (rimane solo la cache). Prima di usarlo in CI va
> creato il file di configurazione; lanciarlo alla cieca riformatterebbero i template con
> impostazioni di default diverse da quelle storiche del progetto.

---

## Test

I test girano dentro container Docker: servono MariaDB, Apache e Chrome headless. Gli script in
`tests/docker/` fanno partire i servizi e poi lanciano i runner.

### PHPUnit — test unitari e d'integrazione

```bash
# nell'ambiente di test (MariaDB + Apache avviati)
php -d memory_limit=-1 vendor/bin/phpunit

# una sola suite
php -d memory_limit=-1 vendor/bin/phpunit --testsuite security
```

Le suite definite in `phpunit.xml`:

| Suite | Percorso | Cosa copre |
|---|---|---|
| `entity` | `tests/UnitTest/Entity` | entità, validazione, `__toString`, getter/setter |
| `extension` | `tests/UnitTest/Twig`, `tests/UnitTest/DQL` | estensioni Twig e funzioni DQL custom |
| `message` | `tests/UnitTest/Message`, `tests/UnitTest/MessageHandler` | messaggi e handler del bus |
| `repository` | `tests/UnitTest/Repository` | query dei repository |
| `security` | `tests/UnitTest/Security` | autenticatori, cifratura, validazione SPID |

I test che toccano il database usano `tests/DatabaseTestCase.php`, che carica le fixture Alice
prima di ogni scenario e fa il purge dopo.

> **Attenzione — stato del database di test.** Le suite che interagiscono con il database
> (`entity`, `repository`, `extension`) caricano un dump SQL pre-generato in `tests/temp/` e
> assumono che il database di test sia **vuoto**. Se il database contiene già dati, la ricarica
> fallisce con `Duplicate entry ... for key '...PRIMARY'` e i test errorano. Prima di eseguirle
> su un database inquinato, azzerare lo schema:
>
> ```bash
> php bin/console doctrine:schema:drop -f --env=test
> php bin/console doctrine:schema:update -f --env=test
> php bin/console app:alice:load _giuaschool --env=test
> ```
>
> Nel container Docker questo non è un problema: l'immagine `test` costruisce il database da
> zero a partire da `src/Install/create-db.sql`.

### Behat — test funzionali sul browser

```bash
php -d memory_limit=-1 vendor/bin/behat
php -d memory_limit=-1 vendor/bin/behat tests/features/avvisi_gestione.feature
```

La configurazione (`behat.yml`) è:

- base URL `https://giuaschool_test` — quindi serve un vhost Apache con **HTTPS** e un
  certificato (c'è un certificato di test in `tests/docker/apache2-certificate.crt`)
- sessione Mink `chrome` (Chrome headless)
- context: `App\Tests\Behat\BrowserContext` (estende `App\Tests\Behat\BaseContext`)

I passaggi in italiano (`Dato login utente "..."`, `Quando vai alla pagina "..."`, `Allora vedi
la pagina "..."`) sono definiti in `BrowserContext`. I dati su cui agire arrivano da fixture
YAML in `tests/features/_*Fixtures.yml`, referenziate da ogni `.feature` e consumate tramite
`BaseContext`.

I test Behat sono **molti e granulari**: 63 file `.feature` in `tests/features/`, coperti dalla CI
in una matrice (un job per feature) per non serializzare la pipeline.

### In Docker (come fa la CI)

```bash
# build dell'immagine di test
docker build --target test -t gs:test -f tests/docker/Dockerfile .

# PHPUnit
docker run --name gs_test \
  --add-host=giuaschool_test:127.0.0.1 --add-host=chrome_headless:127.0.0.1 \
  gs:test tests/docker/test-phpunit.sh

# Behat su una singola feature
docker run --rm --name gs_test \
  --add-host=giuaschool_test:127.0.0.1 --add-host=chrome_headless:127.0.0.1 \
  gs:test tests/docker/test-behat.sh tests/features/avvisi_gestione.feature
```

Gli host `--add-host` servono perché Apache e Chrome devono essere raggiungibili per nome.

### Workflow in CI

`.github/workflows/test-deploy.yml`:

1. **build** — costruisce l'immagine `test` e la salva come tar in cache
2. **test-phpunit** — PHPUnit con coverage Clover,uploadata a Codacy
3. **feature-matrix** — enumera i `.feature` e li distribuisce in matrice
4. **test-behat** — un job per feature, in parallelo
5. **test-update** — solo su `master`: esegue le `.feature` di aggiornamento (`test-update-*.feature`)
6. **deploy** — solo su `master` e solo se i test passano: genera il changelog e lo pubblica nel
   repo della documentazione

Il workflow gira su ogni `push` e su ogni pull request. Il rilascio vero e proprio è un workflow
separato con trigger manuale (`.github/workflows/release.yml`) che pubblica su GHCR le immagini
`prod`, `dev` e `test` con tag di versione.

---

## Struttura del progetto

```
.
├── bin/console                  # CLI Symfony
├── config/                      # configurazione Symfony
│   ├── bundles.php              # bundle attivi per ambiente
│   ├── packages/                # configurazione per bundle (framework, doctrine, security, …)
│   ├── routes.yaml              # rotte degli attributi sui controller
│   ├── services.yaml            # container DI, parametri e binding
│   └── secrets/                 # credenziali esterne (non versionate)
├── public/                      # document root
│   ├── index.php                # front controller
│   ├── install/                 # installer e updater standalone
│   ├── css/ js/ img/ vendor/    # asset statici
│   └── doc/                     # manuali utente in PDF
├── src/
│   ├── Controller/              # 33 controller, 322 rotte
│   ├── Entity/                  # 63 entità Doctrine
│   ├── Repository/              # 64 repository
│   ├── Form/                    # tipi di form Symfony
│   ├── Util/                    # logica di dominio (~26.000 righe)
│   ├── Security/                # autenticatori, cifratura, validazione SPID
│   ├── Message/ MessageHandler/ # bus messaggi e handler
│   ├── EventListener/ Event/    # listener Doctrine e di dominio
│   ├── Command/                 # CLI custom
│   ├── Twig/                    # estensioni Twig
│   ├── DQL/                     # funzioni DQL custom
│   ├── Doctrine/                # tipi Doctrine custom (stringhe cifrate)
│   ├── DataFixtures/            # fixture Alice
│   ├── Install/                 # installer/updater e migrator
│   └── Kernel.php
├── templates/                   # 421 template Twig
├── translations/                # messaggi e validatori in italiano
├── tests/
│   ├── UnitTest/                # PHPUnit
│   ├── features/                # Behat
│   ├── Behat/                   # context Behat
│   └── docker/                  # Dockerfile e script per l'ambiente di test
├── FILES/                       # file caricati, archivi, temporanei (non versionati)
├── PERSONAL/                    # personalizzazione per istituto (non versionata)
├── var/                         # cache, log, sessioni
└── docs/                        # documentazione per sviluppatori
```

`FILES/` e `PERSONAL/` sono le due directory pensate per la personalizzazione da parte
dell'istituto: i template in `PERSONAL/templates/` hanno precedenza su quelli in `templates/`
perché sono registrati prima nel loader Twig, e `PERSONAL/translations/` viene cercata prima di
`translations/`. Entrambe contengono solo `.gitkeep` nel repository.

---

## Convenzioni di codice

**Lingua.** Il codice, i commenti, i messaggi di errore e la documentazione sono in italiano.
I messaggi di log su file usano il formato `Componente: messaggio` (es.
`provisioning-esegue: ERRORE - ...`).

**Licenza.** Ogni file PHP e Twig porta l'intestazione SPDX:

```php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
```

**Struttura interna di una classe.** I metodi sono raggruppati in sezioni marcate da commenti:

```php
  //==================== COSTANTI  ====================
  //==================== ATTRIBUTI DELLA CLASSE  ====================
  //==================== EVENTI ORM ====================
  //==================== METODI PUBBLICI ====================
  //==================== METODI SETTER/GETTER ====================
  //==================== METODI DELLA CLASSE ====================
  //==================== FUNZIONI PRIVATE ====================
```

**Documentazione dei metodi.** Ogni metodo ha un docblock con `@param` e `@return` espliciti,
anche quando il tipo è ovvio. È la convenzione più rigida del progetto: va rispettata.

**Controller.** I controller sono servizi registrati con `controller.service_arguments`: non
usano il dependency injection via costruttore, ma ricevono le dipendenze come **argomenti delle
azioni**, con il tipo comehint:

```php
#[Route(path: '/alunni/modifica/{pagina}', name: 'alunni_modifica', requirements: ['pagina' => '\d+'], defaults: ['pagina' => 0], methods: ['GET', 'POST'])]
#[IsGranted('ROLE_AMMINISTRATORE')]
public function modifica(Request $request, TranslatorInterface $trans, int $pagina): Response {
```

Le dipendenze comuni (`EntityManagerInterface`, `RequestStack`) arrivano da `BaseController`,
che le espone come proprietà `$this->em` e `$this->reqstack`.

**Autorizzazione.** Si usa `#[IsGranted]` sull'attributo della route, non controlli manuali nel
corpo del metodo. Gli attributi disponibili sono definiti in `Utente::getRoles()`.

**Convenzione nomi per i template.** `BaseController::renderHtml($categoria, $azione, ...)`
risolve il template come `{tema?/}{categoria}/{azione}.html.twig`, dove il prefisso di tema vale
`admin/` solo per l'utente amministratore. Quindi:

```
renderHtml('scuola', 'consultazioni')   →   templates/admin/scuola/consultazioni.html.twig
```

`renderCsv($categoria, $azione, $nomefile, ...)` fa lo stesso con `.csv.twig`, anteponendo il
BOM UTF-8 e servendo `Content-Type: text/csv; charset=UTF-8` (indispensabile perché Excel su
Windows interpreti correttamente gli accenti).

**Nomi delle rotte.** `categoria_azione` in snake_case, senza prefisso di ruolo:
`alunni_modifica`, `avvisi_gestione`, `scuola_consultazioni`, `login_form`.

**Filtro CSV.** Nei template `.csv.twig` **non** usare `{% autoescape false %}`: il filtro
`|csv` (`App\Twig\CsvExtension`) già effettua il quoting RFC 4180 e neutralizza la CSV injection
neutralizzando i valori che iniziano con `=`, `+`, `-`, `@`, TAB o CR.

**Stile dei messaggi di commit.** Italiano, imperativo, maiuscolo iniziale, senza prefissi
Conventional Commits:

```
Add new library(vincentlanglet/twig-cs-fixer)
Update tema.css to main.css
Applicato twig-cs-fixer a tutti i template, solo stile
```

---

## Installazione e aggiornamento

L'installazione e l'aggiornamento **non passano dalla CLI Symfony**: sono script standalone in
`public/install/`, eseguiti dal browser, che NON dipendono dal framework e funzionano anche a
database non ancora allineato.

- `public/install/app.php` — installazione iniziale, 6 passi:
  `requirements`, `database`, `schema`, `admin`, `clean`, `end`
- `public/install/update.php` — aggiornamento, 8 passi:
  `unzip`, `fileUpdate`, `requirementsUpdate`, `schemaUpdate`, `envUpdate`, `procedureUpdate`,
  `cleanUpdate`, `endUpdate`

La logica è in `src/Install/Updater.php`; le procedure di migrazione dati complesse in
`src/Install/DataMigrator.php`.

Un file `.gs-updating` nella radice conserva token, versione e build corrente.

### Definire una nuova versione

I file `src/Install/update-vX.Y.Z` (e `update-vX.Y.Z-build` per le build sullo stesso numero)
sono array PHP con quattro chiavi:

```php
return [
  'fileCopy'   => [['sorgente', 'destinazione'], …],
  'fileDelete' => ['percorso', …],
  'sqlCommand' => [['istruzione SQL', 'query di verifica'], …],
  'envDelete'  => ['CHIAVE', …],
  'procedure'  => ['$migrator->nomeMetodo();', …],
];
```

Una nuova versione richiede tre passi:

1. creare `src/Install/update-vX.Y.Z` con SQL e file da applicare
2. scrivere in `DataMigrator` i metodi di migrazione dati non esprimibili in SQL
3. se lo schema cambia, rigenerare `src/Install/create-db.sql` e `drop-db.sql` con
   `doctrine:schema:create --dump-sql` e `doctrine:schema:drop --dump-sql`

> **Attenzione — `eval()` in `Updater.php`.** La sezione `procedure` dei file `update-v*` viene
> eseguita con `eval()`. Le procedure sono di norma semplici chiamate al migrator, ma non tutte:
> `update-v1.6.1` contiene SQL e loop espliciti. Se si aggiunge una procedura, valutare una
> conversione a metodi nominati con allowlist. Vedi `PIANO_SICUREZZA.md` Fase 1.

### L'aggiornamento dal pannello

`SistemaController::aggiorna()` (`/sistema/aggiorna/{step}`, solo amministratore) cerca le release
su GitHub, confronta la versione con i parametri `versione` e `versione_build` del database, e
se necessario delega a `public/install/update.php`.

### Passaggio al nuovo anno scolastico

`SistemaController::nuovo()` (`/sistema/nuovo/{step}`) è una procedura guidata a passi che
riporta al nuovo A.S.: riapre gli scrutini rinviati, sposta circolari/avvisi/documenti nelle
directory dell'anno nuovo, ricrea le classi. Non tocca le tabelle esistenti in modo distruttivo.

---

## Deployment

L'immagine di produzione si costruisce dallo stesso Dockerfile, target `prod`:

```bash
docker build --target prod -t gs:prod -f tests/docker/Dockerfile .
```

Contiene Apache con HTTPS, MariaDB, il progetto in `/var/www/giuaschool` e il document root su
`public/`. Le immagini pubblicate su `ghcr.io/iisgiua/giuaschool` sono buildate da
`.github/workflows/release.yml` con trigger manuale.

Per l'aggiornamento di un'installazione esistente, la procedura prevista è:

1. scaricare lo zip di aggiornamento dalla release GitHub taggata `update-<versione>`
2. decomprimerlo nella directory di progetto
3. aprire `public/install/update.php` dal browser

Il layout dei file sostituiti è calcolato da `tests/docker/update.sh`, che produce lo zip
contenente solo i file il cui md5 è cambiato rispetto alla release precedente.

### Notifiche: esecuzione periodica

Le code di notifica sono su tabella Doctrine (`gs_messenger_messages`), non c'è un worker
forever. Il consumo viene eseguito da una route protetta da token:

```
GET /command/notify/{token}/{time}
```

che lancia `messenger:consume` con `--time-limit`. Va schedulata dall'esterno (cron) con il
token configurato nel parametro `comando_token`. La risposta è `ok` o un errore HTTP.

---

## Documentazione e supporto

- **Manuali per utenti** (alunni, genitori, docenti, ATA): [iisgiua.github.io/giuaschool-docs](https://iisgiua.github.io/giuaschool-docs/),
  e in PDF in `public/doc/`
- **Architettura e modello di dominio**: [docs/ARCHITETTURA.md](docs/ARCHITETTURA.md)
- **Annunci**: [GitHub Discussions](https://github.com/iisgiua/giuaschool/discussions/categories/annunci)
- **Segnalare un errore**: [nuova issue](https://github.com/iisgiua/giuaschool/issues/new?labels=Errore&template=bug-report.md)
- **Chiedere aiuto**: [Discussioni](https://github.com/iisgiua/giuaschool/discussions/new?category=richieste-di-aiuto)
- **Proporre funzionalità**: [Discussioni](https://github.com/iisgiua/giuaschool/discussions/new?category=idee-e-proposte)

### Metadati del progetto

`publiccode.yml` descrive il progetto secondo lo standard [publiccode.yml](https://yml.publiccode.tools):
versione corrente, licenza AGPL-3.0-or-later, categorie, funzionalità, piattaforme
(`spid`, `cie` supportate; `anpr`, `pagopa`, `io` non ancora) e stato di sviluppo.
Va aggiornato almeno a ogni rilascio.

---

## Licenza

AGPL-3.0-or-later. Vedi [LICENSE](LICENSE).

Il progetto include asset con licenze proprie: `public/vendor/bootstrap-slider/LICENSE.md`,
`public/vendor/fontawesome/LICENSE.txt`, `public/vendor/tabler-icons/LICENSE` (MIT, generato da
`tools/tabler_icons.py`) e i font in `public/vendor/bootstrap-italia/fonts/`.