# Analisi dei manuali PDF in `public/doc`

Analisi dei cinque manuali utente distribuiti con l'applicazione, **testo e immagini**,
finalizzata a capirne lo stato di aggiornamento e a guidare una rigenerazione.

## Metodo

- Estrazione del testo con `pdftotext -layout`.
- Estrazione di **tutte** le immagini incorporate con `pdfimages -list` + `pdfimages -png`.
- **OCR** di ogni immagine (RapidOCR/ONNX su CPU) per leggere il contenuto degli screenshot
  e dei pulsanti. Le immagini con testo illeggibile (mascherine `smask`, icone piccolissime)
  restituiscono zero righe: sono segnalate come tali, non interpretate.

L'analisi è stata fatta su una copia di lavoro in `/tmp/pdfanalysis/`; i PDF originali in
`public/doc/` non sono stati modificati.

## Inventario

| File | Pagine | Producer | Immagini | Screenshot | Icone/pulsanti |
|---|---|---|---|---|---|
| `giuaschool_accesso_docenti.pdf` | 8 | LibreOffice 5.1 | 22 | 20 | 2 |
| `giuaschool_manuale_alunni.pdf` | 4 | LibreOffice 6.4 | 18 (9 + 9 mascherine) | 1 | 8 |
| `giuaschool_manuale_ata.pdf` | 2 | LibreOffice 5.1 | 5 | 2 | 3 |
| `giuaschool_manuale_docenti.pdf` | 17 | LibreOffice 5.1 | 112 | 15 | 97 |
| `giuaschool_manuale_genitori.pdf` | 5 | LibreOffice 6.4 | 27 (14 + 13 mascherine) | 3 | 11 |

Totale: **36 pagine**, **184 oggetti immagine**. Il testo è selezionabile e ben recuperabile.
I manuali sono esportati da documenti LibreOffice (writer), non da HTML/Markdown.

## Contenuto testuale, per manuale

### `accesso_docenti` (8 pagine)
Documento monografico sulle quattro modalità di accesso dei docenti:
nome utente/password (bloccato 8–14 nei giorni non festivi), **impronta digitale**,
**Tessera Sanitaria / CNS** e **codice OTP** (Google Authenticator, 2FA). Per ciascuna:
livello di sicurezza, restrizioni, servizi abilitati, procedura di attivazione.
Include la procedura completa di registrazione delle impronte (Protector Suite 2012) e,
per l'OTP, cambio password e recupero password.

### `manuale_alunni` (4 pagine)
Accesso con Google, uscita, gestione utente Google (Apprendere, Classroom, Gmail),
menu Home, Lezioni, Situazione (voti/assenze/note/autorizzazioni), Pagelle, Bacheca
(circolari/avvisi/documenti), Agenda.

### `manuale_ata` (2 pagine)
Accesso con utente/password, uscita, menu e profilo, Circolari e gestione notifiche,
menu Segreteria (Frequenza, Scrutini) con stampa PDF.

### `manuale_docenti` (17 pagine)
Il manuale più ricco: accesso (impronta, tessera sanitaria, OTP da un lato; utente/password
dall'altro), Lezioni (Classe/Materia, Registro, lezioni in compresenza e di sostegno, note
disciplinari, annotazioni, Assenze/appello, Voti, Argomenti, Osservazioni e diario personale,
Scrutinio), Colloqui, Documenti (programmi svolti e relazioni finali), Bacheca, Agenda
(verifiche), Coordinatore (classi, assenze, note, voti, situazione, avvisi, scrutinio).

### `manuale_genitori` (5 pagine)
Accesso, uscita, recupero password, profilo, Home, Lezioni, Situazione (voti, assenze, note,
osservazioni, autorizzazioni), Pagelle, Colloqui (richiesta/conferma/disdetta), Bacheca, Agenda.

## Cosa dicono le immagini

Le immagini sono di due tipi:

1. **Screenshot di pagina** — barra di navigazione, registro, assenze, voti, calendario,
   form di cambio password/OTP, ecc. L'OCR recupera le voci di menu e i dati d'esempio.
2. **Ritagli di pulsanti/icone** — il singolo pulsante o l'icona citata dal testo
   ("clicca su …"). Sono la maggior parte degli oggetti: **97 sui 112** del solo manuale
   docenti.

Esempi ricavati dall'OCR:

- **Barre di menu** con anno scolastico diverso per manuale:
  `A.S. 2017-2018` (ata), `A.S. 2018-2019` (accesso_docenti), `A.S. 2017-2018` (docenti),
  `A.S. 2017-2018` (genitori) e `A.S. 2020/2021` (header login genitori).
- **Registro** (docenti, p.3): colonne *Ora / Materia / Docenti / Argomenti o Attività / Azioni*,
  docenti d'esempio ("Antonello Dessi", "Roberto Musanti", "Lanfranco Puddu").
- **Assenze/appello** (p.6-8): legenda `A / R / G / U`, "Appello degli studenti".
- **Voti** (p.8-9): cursore di voto 1–10 ("Voto 6", slider) e pulsanti `Scritto/Orale/Pratico`.
- **Calendario** (alunni/genitori/docenti): mesi "Marzo/Aprile/Maggio 2018".
- **Circolari** (ata/docenti): filtri "Destinate a me", intervallo date, oggetto.
- **Pulsanti ricorrenti**: `Conferma`, `Annulla`, `Modifica`, `Cancella`, `Aggiungi`,
  `Appello`, `Visualizza`, `Scarica`.
- **accesso_docenti**: screenshot del sistema operativo Windows (Protector Suite 2012,
  "Siti registrati", "Pagina iniziale di Control Center"), pagina di registrazione delle
  impronte e form di attivazione OTP con QR code.

Il logo/intestazione ricorrente è un'immagine di **104×28**, ripetuta nei manuali alunni, ata,
docenti e genitori; la barra di intestazione grande è **2245×252**.

## Obsolescenze e incoerenze rilevate

**Date/versione**
- Gli screenshot mostrano anni scolastici 2017/2018, 2018/2019 e 2020/2021.
- I dati d'esempio sono del 2017 ("lunedì 25 settembre 2017").

**Interfaccia superata**
- Le barre di menu negli screenshot riportano voci che il testo non usa più:
  `Calendario` vs `Agenda`, `Didattica` nel manuale docenti, `+ Situazione` tra Home e Pagelle.
  I menu sono pilotati dal database (`gs_menu_opzione`), quindi questi ritagli si degradano
  a ogni modifica del menu.
- `accesso_docenti` è costruito intorno a **Windows + Protector Suite 2012 + Internet
  Explorer**, cioè a un'immagine delle postazioni scolastiche ormai datata.

**Riferimenti impropri**
- Manuale **alunni** e **genitori**: la giustificazione delle assenze cita il
  *modulo di autodichiarazione correlata all'emergenza pandemica del COVID-19*: da rimuovere.
- Manuale **docenti**, §1: l'URL in "orario non scolastico" è scritto
  `https://registro.giua.gov.it` (dominio **gov.it**) mentre ovunque si usa
  `https://registro.giua.edu.it`. Refuso da correggere.

**Copertura incompleta rispetto al codice**
- Non sono documentati: accesso **SPID/CIE** (gateway MIM), accesso con **Google Workspace**
  per staff/ATA, l'**app mobile**, il recupero/attivazione OTP per gli altri ruoli.
- `accesso_docenti` non accenna ai servizi aggiuntivi oggi abilitati dalle varie modalità.

**Forma**
- Il manuale docenti fa riferimento a pulsanti e icone tramite ritagli immagine: una qualsiasi
  revisione grafica (il ramo corrente è `grafic-refactor`) invalida decine di ritagli in blocco.
- Nei testi alcune icone inline risultano "vuote" nel `pdftotext` (spazi bianchi): sono
  immagini, non testo, e vanno rigenerate insieme al pulsante.

## Raccomandazioni per la rigenerazione

1. **Un'unica sorgente** per i manuali (Markdown o HTML) + pipeline di export, invece di
   documenti LibreOffice mantenuti a mano: rende sostenibile l'aggiornamento periodico.
2. **Screenshot rigenerati** dall'app reale (anno scolastico corrente, menu attuale), e
   almeno per le funzioni di punta; i ritagli-pulsante vanno estratti dagli asset correnti.
3. **Aggiornare i testi**: rimuovere i riferimenti COVID, correggere l'URL `.gov.it`,
   uniformare i nomi dei menu a quelli attuali.
4. **Aggiungere una sezione accessi unica** (utente/password, Google, SPID/CIE, OTP) valida
   per tutti i ruoli, con le restrizioni orarie reali.
5. **Versionare i manuali** con l'anno scolastico e la versione applicativa nel piè di pagina,
   così l'obsolescenza è visibile.

## Limiti dell'analisi

- L'analisi delle immagini è basata su **OCR**, non su ispezione visiva: il testo dentro gli
  screenshot è letto (con qualche errore di riconoscimento), ma colori, layout fine e icone
  puramente grafiche non sono descritti.
- Le immagini molto piccole e le mascherine di trasparenza (`smask`) non producono testo.
- I PDF non contengono form, link o metadati di versione utili oltre al producer.
