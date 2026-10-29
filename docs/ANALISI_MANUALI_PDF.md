# Manuali PDF in `public/doc`

I cinque manuali utente distribuiti con l'applicazione sono stati rigenerati a **ottobre
2026** con le schermate dell'interfaccia corrente e contenuti estesi. I nomi dei file sono
rimasti quelli usati dai template; i vecchi PDF e le varianti `*_ricco_pieno.pdf` sono stati
rimossi.

## Inventario

| File | Pagine | Pubblico | Capitoli |
|---|---|---|---|
| `giuaschool_manuale_docenti.pdf` | 40 | docenti | 16 |
| `giuaschool_manuale_genitori.pdf` | 31 | genitori | 13 |
| `giuaschool_manuale_alunni.pdf` | 29 | alunni | 12 |
| `giuaschool_manuale_ata.pdf` | 22 | personale ATA | 8 |
| `giuaschool_accesso_docenti.pdf` | 21 | tutti gli utenti | 10 |

Totale: **143 pagine**. I PDF sono in formato A4, con testo selezionabile e immagini a colori.

## Struttura dei manuali

Ogni manuale è composto da:

1. **copertina** con logo, titolo, pubblico, istituto, anno scolastico e data di aggiornamento;
2. **presentazione** con la guida alla lettura e la legenda dei riquadri;
3. **indice** su due o tre pagine, con il numero di pagina e i titoli collegati alle sezioni;
4. **capitoli** con banda blu del titolo, introduzione, schermata commentata e riquadri
   «In breve» e «Nota» per le informazioni operative;
5. **piè di pagina** con istituto e «Pagina X di Y».

I metadati PDF riportano titolo, autore e descrizione del manuale.

Le schermate sono state catturate dall'ambiente demo dell'istituto **I.I.S. Michele Giua**:
nomi, classi e dati sono di esempio. Nell'istituto reale cambiano; anche l'interfaccia può
variare in base alla configurazione e alle funzioni attive.

Il set `_demo` delle fixture è pensato per i test automatici e contiene testi segnaposto
(«demo», «mini», lorem ipsum): prima della cattura lo script
`tools/giuaschool_tools/manuali/dati_demo.py` sostituisce nomi, argomenti, note, moduli,
circolari, colloqui e recapiti con dati scolastici coerenti, usando l'istituto, le sedi e i
dati di contatto reali. Le fixture del progetto non vengono modificate.

## Contenuti per manuale

### `giuaschool_manuale_docenti.pdf` (40 pagine)
1. Accesso e profilo
2. La Home e il menu principale
3. Il registro di classe
4. Voti e valutazioni
5. Assenze, ritardi e appello
6. Note disciplinari
7. Argomenti e programma svolto
8. Osservazioni e diario personale
9. Scrutinio e proposte di voto
10. Colloqui con le famiglie
11. Documenti e piani di lavoro
12. Bacheca: circolari, avvisi e documenti
13. Agenda, verifiche e compiti
14. Funzioni del coordinatore
15. L'app giua@school/app
16. Sicurezza, assistenza e glossario

### `giuaschool_manuale_genitori.pdf` (31 pagine)
1. Accesso e profilo
2. La Home e le notifiche
3. Lezioni e argomenti
4. Situazione: voti
5. Situazione: assenze e giustificazioni
6. Note, osservazioni e autorizzazioni
7. Pagelle e scrutini
8. Colloqui con i docenti
9. Bacheca: circolari, avvisi e documenti
10. Moduli e richieste
11. Agenda e appuntamenti
12. L'app giua@school/app
13. Sicurezza, assistenza e glossario

### `giuaschool_manuale_alunni.pdf` (25 pagine)
1. Accesso e profilo
2. La Home e il menu
3. Lezioni e argomenti
4. Situazione: voti
5. Assenze e giustificazioni
6. Note e autorizzazioni
7. Pagelle e scrutini
8. Moduli e richieste
9. Bacheca: circolari, avvisi e documenti
10. Agenda e scadenze
11. L'app giua@school/app
12. Sicurezza, assistenza e glossario

### `giuaschool_manuale_ata.pdf` (22 pagine)
1. Accesso e profilo
2. La Home e il menu
3. Bacheca e circolari
4. Segreteria: frequenza degli alunni
5. Segreteria: scrutini
6. Segreteria: utenti genitori
7. L'app giua@school/app
8. Sicurezza, assistenza e glossario

### `giuaschool_accesso_docenti.pdf` (21 pagine)
1. Il Registro Elettronico
2. Accedere con nome utente e password
3. Accedere con Google
4. Accedere con SPID, CIE ed eIDAS
5. Recuperare la password
6. Cambiare la password
7. Profilo e notifiche
8. Uscire dal registro
9. L'app giua@school/app
10. Sicurezza, assistenza e glossario

## La pagina `/api/info/app`

La pagina «Informazioni App» è documentata nel capitolo **L'app giua@school/app** di tutti i
manuali; nel manuale di accesso è il capitolo 9. La schermata
`docs/manuali/img/comune/app.png` riproduce lo stato attuale:

- riquadro verde con la versione disponibile (`giua@school/app v1.3.4`);
- testo bianco su fondo verde, con le istruzioni «Come si installa?» e «Come si usa?»;
- pulsante blu **«Scarica»** con l'icona Android, cliccabile, nella riga «Sistema operativo».

Il download usa l'APK storico distribuito dalla scuola
(`public/app/giuaschool-app-v1.3.4.apk`). Il capitolo descrive requisiti, download,
installazione da fonti sconosciute, configurazione al primo avvio, uso quotidiano,
aggiornamento, sicurezza, problemi frequenti e disinstallazione.

## Rigenerazione

Il package `tools/giuaschool_tools/manuali/` contiene i testi (`contenuti.py`, con la
costante `MANUALI` e i capitoli comuni costruiti da `capitolo_app()` e
`capitolo_sicurezza()`) e la cattura delle schermate (`cattura.py`).
`tools/crea_manuali_pdf.py` impagina i PDF con ReportLab + Pillow e importa i due moduli
dal package; non servono esportazioni intermedie né PHP.

Le immagini sono in `docs/manuali/img` e possono essere ricatturate con `--cattura`
dall'istanza locale dell'applicazione (Playwright). La cattura usa gli utenti demo
indicati dalle variabili `GS_DOCENTE`, `GS_GENITORE`, `GS_ALUNNO` e `GS_ATA` (default
`docente`, `genitore`, `alunno`, `ata`); per il ruolo docenti serve `login_speciale = 1`
oppure uno storage state Playwright con una sessione Google già autenticata.

> Nota: `tools/` e `docs/manuali/img` sono esclusi dal versionamento perché raccolgono
> strumenti e immagini di lavoro locali. I PDF in `public/doc` sono il deliverable
> versionato; per rigenerarli servono gli script locali e le immagini, che vanno ricatturate
> o ripristinate da un backup.

```bash
# 1) dataset demo presentabile, 2) schermate, 3) PDF
export GS_DOCENTE=ciro.deluca GS_GENITORE=lauro.parisi.f \
       GS_ALUNNO=lauro.parisi.s GS_ATA=lucilla.mariani.a
php bin/console app:alice:load _demo
python3 -m tools.giuaschool_tools.manuali.dati_demo
python3 tools/crea_manuali_pdf.py --cattura

# tutti i manuali, direttamente in public/doc, senza ricatturare
python3 tools/crea_manuali_pdf.py

# un solo manuale, in una cartella di prova
python3 tools/crea_manuali_pdf.py --solo comune --output-dir /tmp/opencode/pdf-nuovi

# ricattura le schermate prima di generare (richiede Playwright e l'ambiente demo)
python3 tools/crea_manuali_pdf.py --cattura
```

Il generatore fa due passate: la prima misura le pagine dei capitoli, la seconda compila
l'indice con i numeri definitivi e i collegamenti interni. Se la seconda passata cambia
la lunghezza dei capitoli, ripete l'impaginazione fino a stabilizzare i numeri dell'indice.
Con `--debug` stampa le informazioni di impaginazione di ogni capitolo.

Dopo la generazione:

```bash
pdfinfo public/doc/giuaschool_manuale_docenti.pdf
pdftotext -layout public/doc/giuaschool_manuale_docenti.pdf - | less
pdftoppm -r 96 -png -f 1 -l 4 public/doc/giuaschool_manuale_docenti.pdf /tmp/manuale
pdftohtml -xml -i -stdout -f 3 -l 4 public/doc/giuaschool_manuale_docenti.pdf | grep href
```

## Verifiche eseguite

- Conteggio pagine e metadati con `pdfinfo`: 143 pagine complessive
  (40 + 31 + 29 + 22 + 21), tutte in formato A4.
- Controllo delle pagine documentate in ogni schermata catturata: le pagine di tutti i
  ruoli (docente, genitore, alunno, ATA) sono state aperte con gli utenti demo e il testo
  di `#gs-main` verificato riga per riga, senza residui di testi segnaposto.
- Estrazione del testo con `pdftotext -layout` per tutti i manuali.
- Controllo dei collegamenti dell'indice con `pdftohtml`: ogni voce punta alla pagina
  stampata accanto al titolo.
- Confronto con i PDF prodotti dal precedente generatore PHP/TCPDF, poi rimosso: stessi
  numeri di pagina e stesso testo per pagina, con differenze visive limitate ai metadati
  del producer.
- Verifica dei segnalibri: ogni voce dell'indice ha un segnalibro che apre la stessa pagina.
- Ispezione visiva di copertine, indici, capitoli campione e della pagina dell'app.
- Verifica del download dell'APK dalla pagina `/api/info/app`: risposta `200 OK` con
  `Content-Type: application/vnd.android.package-archive`.
- Assenza delle procedure obsolete basate su impronta digitale, Tessera Sanitaria/CNS,
  Protector Suite o Internet Explorer, e dei riferimenti all'emergenza COVID-19.
- Corretto il link al manuale degli alunni nelle pagine di accesso, che usava la data del
  manuale dei genitori.

## Analisi dei PDF precedenti (storico)

I PDF sostituiti erano esportazioni LibreOffice del 2019 e del 2021, per un totale di 36
pagine. Le criticità principali erano:

- screenshot fermi agli anni scolastici 2017/2018, 2018/2019 e 2020/2021;
- procedure legate a Windows, Protector Suite 2012, impronta digitale e Tessera Sanitaria;
- URL `registro.giua.gov.it` nel manuale docenti (dominio corretto: `registro.giua.edu.it`);
- riferimento al modulo COVID-19 nella giustificazione delle assenze;
- refusi ricorrenti («è importare uscire», «la presenze di assenze»);
- pulsanti e icone inseriti come ritagli immagine, quindi non ricercabili e fragili ai
  cambi di grafica;
- nessuna documentazione dell'app mobile e delle modalità di accesso Google Workspace per il
  personale, SPID e CIE;
- nessun piè di pagina con data o versione.

La rigenerazione ha risolto questi punti: i testi sono centralizzati, le schermate sono
attuali, i riferimenti ai pulsanti sono testo e non ritagli immagine, l'indice è collegato e
ogni manuale riporta anno scolastico e data di aggiornamento.

## Limiti

- Le schermate provengono dall'ambiente demo, non da un'installazione di produzione: vanno
  verificate le voci di menu e le funzioni effettivamente abilitate presso l'istituto.
- La versione dell'APK e la data dei manuali possono cambiare senza che questo documento
  venga aggiornato: la fonte autorevole è sempre il PDF distribuito in `public/doc`.
