# AGENTS.md — giua@school

Scuola elettronica open source dell'I.I.S. Michele Giua. Monolito modulare Symfony 6.4, PHP >= 8.2, Doctrine ORM ^3.3, MySQL/MariaDB. Nessun micro-servizio, nessun Node.js/npm.

## Stack e requisiti

- PHP >= 8.2 con estensioni `ctype`, `iconv` (MySQL/MariaDB driver richiesto per il database)
- Composer 2
- Database MySQL 5.7+ o MariaDB 10.3+
- Solo test funzionali (Behat): Google Chrome headless sulla porta 9222

## Setup locale

```
composer install
cp .env.example .env.local        # poi imposta APP_SECRET e DATABASE_URL in .env.local
php bin/console doctrine:database:create
php bin/console doctrine:schema:update -f
php bin/console app:alice:load _giuaschool   # fixture minime (utente admin)
php bin/console cache:clear
php -S localhost:8000 -t public   # document root: public/
```

## Ambiente di test

I test girano in `APP_ENV=test` e usano `.env.test` (non `.env.local`). Servono MariaDB, Apache e Chrome headless. La CI li esegue in Docker (`tests/docker/Dockerfile`, target `test`).

### PHPUnit (test unitari e d'integrazione)

```bash
php -d memory_limit=-1 vendor/bin phpunit
php -d memory_limit=-1 vendor/bin/phpunit --testsuite security   # singola suite
```

Suite: `entity`, `extension`, `message`, `repository`, `security`, `util`. I test DB usano `tests/DatabaseTestCase.php` (fixture Alice prima di ogni scenario).

> Attenzione: le suite che toccano il database caricano dump SQL da `tests/temp/` e presuppongono database vuoto. Se il DB di test è inquinato, falliscono con `Duplicate entry`. Ripristina con:
> ```
> php bin/console doctrine:schema:drop -f --env=test
> php bin/console doctrine:schema:update -f --env=test
> php bin/console app:alice:load _giuaschool --env=test
> ```

### Behat (browser, Chrome headless)

```bash
php -d memory_limit=-1 vendor/bin/behat
php -d memory_limit=-1 vendor/bin/behat tests/features/avvisi_gestione.feature
```

Base URL `https://giuaschool_test` (serve vhost Apache con HTTPS e certificato; c'è uno di test in `tests/docker/apache2-certificate.crt`). Sessione Mink `chrome` (DevTools porta 9222).

### In Docker (come fa la CI)

```bash
docker build --target test -t gs:test -f tests/docker/Dockerfile .
docker run --name gs_test --add-host=giuaschool_test:127.0.0.1 --add-host=chrome_headless:127.0.0.1 gs:test tests/docker/test-phpunit.sh
docker run --rm --name gs_test --add-host=giuaschool_test:127.0.0.1 --add-host=chrome_headless:127.0.0.1 gs:test tests/docker/test-behat.sh tests/features/avvisi_gestione.feature
```

## Qualità del codice

```
vendor/bin/psalm                   # analisi statica (errorLevel 5, baseline psalm-baseline.xml)
vendor/bin/rector --dry-run        # refactoring (config: rector.php)
vendor/bin/twig-cs-fixer lint      # stile template (config: .twig-cs-fixer.dist.php)
vendor/bin/twig-cs-fixer fix       # applica correzioni stile
typos                              # controllo ortografico (binario esterno: crate-ci/typos)
```

Nessuno di questi è un comando npm: il progetto non ha `package.json`.

## Convenzioni (importanti)

- Codice, commenti, messaggi di errore, documentazione: **italiano**.
- Ogni file PHP e Twig porta l'intestazione SPDX (`AGPL-3.0-or-later`).
- Docblock con `@param` e `@return` espliciti su ogni metodo, anche se il tipo è ovvio.
- Controller: servizi con `controller.service_arguments`; le dipendenze arrivano come **argomenti di azione** con typehint (non via costruttore). `BaseController` espone `$this->em` e `$this->reqstack`.
- Autorizzazione: `#[IsGranted(...)]` sull'attributo della route, non controlli manuali nel metodo. Ruoli Symfony (`ROLE_AMMINISTRATORE`, ecc.) e codici ruolo a una lettera (`Utente::getCodiceRuolo()`) coesistono: `controllaRuolo('ADG')` usa `str_contains`.
- Template: `renderHtml('categoria', 'azione')` → `templates/admin/categoria/azione.html.twig` per l'admin.
- Route: `categoria_azione` in snake_case, senza prefisso di ruolo (`alunni_modifica`, `avvisi_gestione`).
- Template `.csv.twig`: **non** usare `{% autoescape false %}`; usa il filtro `|csv` (quoting RFC 4180, neutralizza CSV injection).
- Messaggi di commit: italiano, imperativo, maiuscolo iniziale, niente prefissi Conventional Commits.

## Schema / database

Non c'è `doctrine/migrations`. Lo schema si aggiorna con `doctrine:schema:update -f` (dev/test). In produzione gli aggiornamenti passano dagli script standalone in `public/install/` (`install.php`, `update.php`) e dai file `src/Install/update-vX.Y.Z`.

## Variabili d'ambiente

Precedenza: variabili reali → `.env.local` / `.env.test` → `.env.example`. `.env.local` è ignorato da git e contiene credenziali. `APP_SECRET` è anche chiave AES per i campi cifrati (`Encryptor` / `EncryptedStringType`): ruotarlo con `php bin/console app:sicurezza:ruota-segreto --conferma` richiede ricifratura dei dati.

## Directory rilevanti

- `src/` — codice applicativo (Controller, Entity, Repository, Util, Security, Command, DataFixtures, Install, …)
- `templates/` — 421 template Twig (precedenza: `PERSONAL/templates/` prima di `templates/`)
- `tests/` — PHPUnit (`UnitTest/`), Behat (`features/`, `Behat/`), docker (`docker/`)
- `config/` — configurazione Symfony (non modificare a mano se non necessario)
- `public/` — document root, front controller `index.php`, installer standalone (`install/`)
- `FILES/`, `PERSONAL/`, `var/` — non versionati

## Documentazione

- Architettura e dominio: `docs/ARCHITETTURA.md`
- Manuali utente: `iisgiua.github.io/giuaschool-docs` (repo separato), PDF in `public/doc/`
