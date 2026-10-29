# Comandi di verifica

Checklist dei comandi da eseguire dalla radice del progetto per verificare che
codice, template, traduzioni, test e manuali siano coerenti.

> **Attenzione — database di test locale**
> In questo workspace `.env.local` e `.env.test` puntano allo stesso database
> `giuaschool`. Le suite PHPUnit che usano `tests/DatabaseTestCase.php` azzerano
> e ricaricano il database: possono cancellare i dati demo. Per non perdere i
> dati usare Docker (opzione A) oppure un database separato (opzione B).
> Non lanciare `doctrine:schema:drop --env=test` sul database che contiene i
> dati demo.

## 0. Prerequisiti

- PHP >= 8.2 con `ctype`, `iconv`, `intl`, `pdo_mysql`
- Composer 2
- MariaDB/MySQL e Apache per i test locali
- Google Chrome headless sulla porta 9222 per Behat
- `poppler-utils` (`pdfinfo`, `pdftotext`, `pdftohtml`) per i controlli PDF
- `typos` (binario esterno, vedi `typos.toml`)
- Node.js solo per il controllo sintattico dei blocchi JavaScript nei template

Installazione delle dipendenze:

```bash
composer install
```

## 1. Controlli rapidi (gate minimo)

```bash
(
  set -e
  php bin/console lint:twig templates
  php bin/console lint:yaml translations config
  php bin/console lint:container
  vendor/bin/twig-cs-fixer lint
  typos
  git diff --check
  composer validate --no-check-publish
  echo "CONTROLLI RAPIDI: OK"
)
```

Esito atteso:

- 402 file Twig con sintassi valida;
- 27 file YAML con sintassi valida;
- container Symfony valido;
- 402 template conformi a `twig-cs-fixer`, 0 errori;
- `typos` senza segnalazioni;
- nessun errore di spaziature in `git diff --check`;
- `composer.json` valido.

## 2. Analisi statica

```bash
vendor/bin/psalm --no-progress --show-info=false
vendor/bin/rector --dry-run --no-progress-bar
```

Stato noto:

- **Psalm** riporta 13 errori già presenti nel working tree (1 `InvalidArgument`,
  5 `PossiblyUnusedMethod`, 1 `UnusedClass`, 6 `UnusedBaselineEntry`) e 8156
  segnalazioni informative. Non è un gate verde finché la baseline non viene
  rigenerata o gli errori non vengono corretti.
- **Rector** in `--dry-run` propone modifiche su 6 file: è un'anteprima, non un
  errore. Per applicarle, dopo aver controllato il diff, usare
  `vendor/bin/rector`.

## 3. PHPUnit

### Opzione A — Docker (consigliata, non tocca il database locale)

```bash
docker build --target test -t gs:test -f tests/docker/Dockerfile .

docker run --rm --name gs_test \
  --add-host=giuaschool_test:127.0.0.1 \
  --add-host=chrome_headless:127.0.0.1 \
  gs:test tests/docker/test-phpunit.sh
```

Il container avvia MariaDB e Apache, azzera il database e carica le fixture.
L'esito atteso è l'intera suite PHPUnit verde con copertura Clover in
`clover.xml`.

### Opzione B — Locale, con database di test separato

Creare una sola volta `.env.test.local` (è ignorato da Git) con un database
dedicato:

```bash
cat > .env.test.local <<'EOF'
DATABASE_URL='mysql://root:root@localhost:3306/giuaschool_test'
EOF
```

Preparare il database di test:

```bash
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:schema:update -f --env=test
php bin/console app:alice:load _giuaschool --env=test
```

Eseguire i test:

```bash
# intera suite
php -d memory_limit=-1 vendor/bin/phpunit

# singole suite
for suite in controller entity extension message repository security util; do
  echo "== $suite"
  php -d memory_limit=-1 vendor/bin/phpunit --testsuite "$suite"
done
```

Se compare `Duplicate entry ... for key ...PRIMARY`, il database di test è
inquinato. Azzerare **solo** il database separato:

```bash
php bin/console doctrine:schema:drop -f --env=test
php bin/console doctrine:schema:update -f --env=test
php bin/console app:alice:load _giuaschool --env=test
```

## 4. Behat

### Opzione A — Docker (consigliata)

Una singola feature:

```bash
docker run --rm --name gs_test \
  --add-host=giuaschool_test:127.0.0.1 \
  --add-host=chrome_headless:127.0.0.1 \
  gs:test tests/docker/test-behat.sh tests/features/avvisi_gestione.feature
```

Tutte le feature (lungo: la CI le distribuisce in una matrice):

```bash
docker run --rm --name gs_test \
  --add-host=giuaschool_test:127.0.0.1 \
  --add-host=chrome_headless:127.0.0.1 \
  gs:test tests/docker/test-behat.sh
```

### Opzione B — Locale

Servono MariaDB, Apache con vhost HTTPS `https://giuaschool_test`, Chrome
headless sulla porta 9222 e il database di test separato dell'opzione 3B.

```bash
google-chrome --headless=new --disable-gpu --disable-software-rasterizer \
  --disable-dev-shm-usage --no-sandbox --remote-debugging-address=0.0.0.0 \
  --remote-debugging-port=9222 --window-size=1920,1080 \
  --ignore-certificate-errors 2>/dev/null &

php bin/console cache:clear --env=test
php -d memory_limit=-1 vendor/bin/behat

# una sola feature
php -d memory_limit=-1 vendor/bin/behat tests/features/avvisi_gestione.feature

# fermarsi al primo errore
php -d memory_limit=-1 vendor/bin/behat tests/features/avvisi_gestione.feature \
  --stop-on-failure -f progress
```

Verifica che Chrome risponda:

```bash
curl -sS http://localhost:9222/json/version
```

## 5. Test della procedura di aggiornamento

Solo se sono stati modificati `public/install/`, gli script di update o le
fixture di aggiornamento:

```bash
docker run --rm --name gs_test \
  --add-host=giuaschool_test:127.0.0.1 \
  --add-host=chrome_headless:127.0.0.1 \
  gs:test tests/docker/test-update.sh
```

## 6. Smoke test HTTP

Avviare l'applicazione, se non è già in esecuzione:

```bash
symfony server:start --document-root=public
# oppure
php -S localhost:8000 -t public
```

Controllare pagina app, APK e PDF:

```bash
curl -sS -o /dev/null -w 'app: %{http_code} %{content_type}\n' \
  http://localhost:8000/api/info/app

curl -sS http://localhost:8000/api/info/app \
  | grep -q '<strong class="gs-big">Scarica</strong>' \
  && echo 'pulsante Scarica: OK'

curl -sS -I http://localhost:8000/app/giuaschool-app-v1.3.4.apk | head -1

for f in public/doc/*.pdf; do
  curl -sS -o /dev/null \
    -w "pdf $(basename "$f"): %{http_code} %{content_type}\n" \
    "http://localhost:8000/doc/$(basename "$f")"
done
```

Esito atteso: `200`, `text/html` per la pagina app,
`application/vnd.android.package-archive` per l'APK e `application/pdf` per i
cinque manuali.

## 7. Controlli sui PDF

Pagine e presenza della sezione dedicata all'app:

```bash
for f in public/doc/*.pdf; do
  printf '%-42s ' "$(basename "$f")"
  pdfinfo "$f" | awk '/^Pages:/ {printf "%s pagine, ", $2} /^Page size:/ {print $3"x"$5" pt"}'
  pdftotext -layout "$f" - | grep -q "La pagina «Informazioni App»" \
    && echo "  sezione app: OK" \
    || echo "  sezione app: MANCANTE"
done
```

Verifica che i numeri di pagina dell'indice corrispondano alle pagine reali:

```bash
python3 - <<'PY'
import re
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

totale_errori = 0
for pdf in sorted(Path('public/doc').glob('*.pdf')):
    xml = subprocess.run(
        ['pdftohtml', '-xml', '-i', '-stdout', str(pdf)],
        capture_output=True, text=True, check=True
    ).stdout
    root = ET.fromstring(xml)
    voci = 0
    errori_pdf = 0
    for page in root.findall('page'):
        testi = page.findall('text')
        if not any('Indice' in ''.join(t.itertext()) for t in testi):
            continue
        for t in testi:
            a = t.find('a')
            if a is None or not a.get('href'):
                continue
            m = re.search(r'#(\d+)$', a.get('href'))
            if not m:
                continue
            top = float(t.get('top'))
            numeri = [
                ''.join(x.itertext()).strip()
                for x in testi
                if ''.join(x.itertext()).strip().isdigit()
                and abs(float(x.get('top')) - top) <= 2.5
                and float(x.get('left')) > 700
            ]
            if not numeri:
                continue
            voci += 1
            if int(m.group(1)) != int(numeri[0]):
                errori_pdf += 1
                print('ERRORE', pdf.name, 'pagina', page.get('number'),
                      ''.join(a.itertext()), m.group(1), numeri[0])
    print(f'{pdf.name}: {voci} voci, {errori_pdf} errori')
    totale_errori += errori_pdf
raise SystemExit(1 if totale_errori else 0)
PY
```

Esito atteso: `0 errori` per tutti i PDF.

## 8. Rigenerazione dei manuali (opzionale)

Richiede le immagini sorgente in `docs/manuali/img` e i pacchetti Python `reportlab` e
`pillow` (`python3 -m pip install reportlab pillow`). Solo per `--cattura` servono anche
Playwright con Chromium (`python3 -m pip install playwright`,
`python3 -m playwright install chromium`) e l'ambiente demo raggiungibile su
`http://localhost:8000` con gli utenti demo.

Le schermate vanno catturate da un dataset presentabile: le fixture `_demo` contengono
testi segnaposto, che `dati_demo.py` sostituisce senza modificare le fixture del progetto.

```bash
export GS_DOCENTE=ciro.deluca GS_GENITORE=lauro.parisi.f \
       GS_ALUNNO=lauro.parisi.s GS_ATA=lucilla.mariani.a
php bin/console app:alice:load _demo
python3 -m tools.giuaschool_tools.manuali.dati_demo

# genera i PDF in una directory temporanea
python3 tools/crea_manuali_pdf.py --output-dir /tmp/opencode/pdf-nuovi

# ricattura le schermate e poi genera (solo il gruppo comune)
python3 tools/crea_manuali_pdf.py --cattura --solo comune --output-dir /tmp/opencode/pdf-nuovi

# installa i PDF definitivi
cp /tmp/opencode/pdf-nuovi/*.pdf public/doc/
```

Dopo l'installazione rieseguire i controlli del punto 7 (sezione app e indice): i numeri
dell'indice devono restare coerenti con le pagine reali di tutti i cinque manuali.

Se `docs/manuali/img` non esiste, le schermate vanno ricatturate con `--cattura`
oppure ripristinate da un backup prima di lanciare `crea_manuali_pdf.py`.
Sia `tools/` sia `docs/manuali/img` sono esclusi dal versionamento: i PDF in
`public/doc` sono l'unico deliverable del repository.

## 9. Smoke test mirato delle pagine con JavaScript inline

Verifica le due pagine corrette (datepicker delle lezioni e dati iniziali delle
assenze) con un genitore demo:

```bash
python3 - <<'PY'
import os
from playwright.sync_api import sync_playwright

utente = os.environ.get('GS_UTENTE', 'achille.pace.f')
password = os.environ.get('GS_PASSWORD', utente)

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page(viewport={'width': 1366, 'height': 900})
    errori = []
    page.on('console', lambda m: errori.append(m.text) if m.type == 'error' else None)
    page.on('pageerror', lambda e: errori.append(str(e)))

    page.goto('http://localhost:8000/login/form/', wait_until='domcontentloaded')
    page.fill('#username', utente)
    page.fill('#password', password)
    page.click('button[type=submit]')
    page.wait_for_timeout(1500)

    page.goto('http://localhost:8000/genitori/lezioni', wait_until='networkidle')
    assert page.evaluate("!!$('#gs-button-date').data('datepicker')"), 'datepicker assente'
    page.click('#gs-button-date')
    page.wait_for_timeout(300)
    assert page.is_visible('.datepicker'), 'calendario non visibile'

    page.goto('http://localhost:8000/genitori/assenze', wait_until='networkidle')
    assert page.evaluate("!!document.querySelector('#gs-pagina-dati')"), 'dati iniziali assenti'

    assert not errori, f'errori JavaScript: {errori}'
    print('Smoke test JavaScript: OK')
    browser.close()
PY
```

## 10. Riepilogo dello stato

| Controllo | Comando | Stato atteso |
|---|---|---|
| Sintassi Twig | `php bin/console lint:twig templates` | OK, 402 file |
| Sintassi YAML | `php bin/console lint:yaml translations config` | OK, 27 file |
| Container Symfony | `php bin/console lint:container` | OK |
| Stile Twig | `vendor/bin/twig-cs-fixer lint` | OK, 0 errori |
| Ortografia | `typos` | OK |
| Spaziature Git | `git diff --check` | OK |
| Composer | `composer validate --no-check-publish` | valido |
| Psalm | `vendor/bin/psalm --no-progress --show-info=false` | 13 errori noti, non verde |
| Rector | `vendor/bin/rector --dry-run` | 6 file proposti, anteprima |
| PHPUnit | Docker o DB separato | verde |
| Behat | Docker o vhost HTTPS + Chrome | verde |
| HTTP app/APK/PDF | `curl` | 200 |
| PDF e indice | `pdfinfo` / `pdftotext` / `pdftohtml` | 0 errori |
