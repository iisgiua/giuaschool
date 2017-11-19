# giua@school

*il Registro Elettronico dell'Istituto di Istruzione Superiore "Michele Giua"*


## IL PROGETTO

Il progetto nasce dalla volontà di utilizzare un Registro Elettronico *open
source* per le attività scolastiche dell'**Istituto di Istruzione Superiore
"Michele Giua"**.

Il primo candidato è stato l'ottimo [Lampschool](http://www.lampschool.it/), al
quale sono state apportate parecchie modifiche per renderlo idoneo alle
esigenze dell'Istituto, tra cui le principali sono:
  - gestione di due sedi scolastiche, con docenti che possono lavorare anche in entrambe le sedi;

  - orario scolastico differente per sede, con unità orarie anche da 90 minuti, la prima o l'ultima a seconda della sede;

  - orario provvisorio di inizio anno con solo 4 unità orarie da 60 minuti;

  - il calcolo delle ore di assenza degli studenti, come pure la visualizzazione del registro,
    deve tenere conto dell'orario in uso (quello provvisorio o quello definitivo) e
    della sede a cui appartiene la classe;

  - gestione dello scrutinio, comprese le problematiche della non ammissione per
    superamento del limite delle assenze o dell'eventuale deroga;

  - gestione dell'importazione e dell'esportazione dei dati da e verso il sistema proprietario in uso nella Segreteria Alunni.

Successivamente, poiché le modifiche stavano diventando sempre più numerose, si
è deciso di creare un nuovo progetto basato su strumenti più idonei per la
gestione di un codice ormai troppo complesso.

Nasce così **giua@school** che, sebbene sia stato riscritto da zero, può essere
considerato una *fork* di [Lampschool](http://www.lampschool.it/), perché
riprende da questo diverse soluzioni algoritmiche e l'importante esperienza
maturata con il suo uso.

Il progetto **giua@school** è basato sull'uso di:
  - [Symfony](https://symfony.com/) per la gestione generale del sistema e in particolare della sicurezza;
  - [Doctrine](http://www.doctrine-project.org/) per la gestione del livello di astrazione del database;
  - [Twig](https://twig.symfony.com/) per la gestione dei *template*;
  - [Bootstrap](https://getbootstrap.com/) per l'interfaccia grafica.


## REQUISITI DI SISTEMA

I requisiti minimi per l'installazione sono quelli richiesti da *Symfony 3.3*:
  - web server **Apache 2.x** o superiore;
  - database server **MySQL 5.5** o superiore, o versioni equivalenti di
    **MariaDB** (sono supportate anche altre piattaforme, ma non sono state
    testate con *giua@school*)
  - **PHP 5.5.9** o superiore (**ATTENZIONE: *giua@school* non è stato testato con PHP 7.x**);
  - ulteriori requisiti minori.

Per semplificare le cose, *Symfony* mette a disposizione uno strumento di
verifica dei requisiti, come meglio specificato nella sezione
dell'installazione.


## INSTALLAZIONE

Per provare *giua@school* si consiglia l'installazione in locale, sul proprio
computer, seguendo i passi descritti di seguito.


### 1. Installazione del web server e del database server

Scaricare [XAMPP](https://www.apachefriends.org/it/download.html), facendo
attenzione a scegliere la versione per il proprio sistema operativo che
includa il **PHP 5.x** (non quelle con il *PHP 7.x*).

Installare XAMPP sul proprio computer; in caso di difficoltà consultare la
sezione delle FAQ presente sul loro sito.

È sufficente installare i seguenti componenti:
  - Apache
  - MySQL/MariaDB
  - PHP

Al termine dell'installazione avviare il server database **MySQL/MariaDB** usando il *Control Panel* di
XAMPP.


### 2. Installazione di giua@school

Scaricare sul proprio computer l'ultima versione disponibile di *giua@school*,
quindi estrarre i file nella cartella usata da XAMPP per contenere i siti web.
Normalmente il percorso di questa cartella è simile a quanto segue:
```
### SISTEMI WINDOWS
<percorso_installazione_xampp>\xampp\htdocs

### SISTEMI LINUX
<percorso_installazione_xampp>/lampp/htdocs
```

Aprire una finestra di terminale (o *Prompt dei comandi* per
i sistemi Windows) e posizionarsi all'interno della cartella dove si sono
estratti i file di *giua@school*.
Ad esempio, il comando per posizionarsi nella cartella "giua-school", dove si trovano i file
dell'applicazione, sarà il seguente:
```
### SISTEMI WINDOWS
cd <percorso_installazione_xampp>\xampp\htdocs\giua-school

### SISTEMI LINUX
cd <percorso_installazione_xampp>/lampp/htdocs/giua-school
```

Sempre dalla finestra di terminale, eseguire il seguente comando per verificare che i requisiti di sistema siano
corretti:
```
### SISTEMI WINDOWS
<percorso_installazione_xampp>\xampp\php\php bin\symfony_requirements

### SISTEMI LINUX
<percorso_installazione_xampp>/lampp/bin/php bin/symfony_requirements
```
Nel caso siano mostrati degli errori, sarà necessario
installare i componenti mancanti prima di continuare.

In particolare, se si usa Windows e risulta non installata l'estensione **intl**, aprire con un
editor di testo il file:
```
<percorso_installazione_xampp>\xampp\php\php.ini
```
Quindi cercare la riga seguente, rimuovere il punto e virgola iniziale (carattere ";") e salvare il file:
```
;extension=php_intl.dll
```

Se invece si usa Linux e risulta non installata l'estensione **intl**, dovrebbe
essere sufficiente eseguire il seguente comando dalla finestra di terminale:
```
sudo apt-get install php-intl
```

**ATTENZIONE:** se anziché una nuova installazione di XAMPP si è scelto di utilizzare
un'installazione pre-esistente di MySQL/MariaDB, sarà necessario
modificare i parametri di connessione al database, che si trovano nel file:
```
### SISTEMI WINDOWS
<percorso_installazione_giua-school>\app\config\parameters.yml

### SISTEMI LINUX
<percorso_installazione_giua-school>/app/config/parameters.yml
```


### 3. Creazione del database di giua@school

Per creare il database di *giua@school* eseguire i seguenti comandi dalla
finestra di terminale:
```
### SISTEMI WINDOWS
<percorso_installazione_xampp>\xampp\php\php bin\console doctrine:database:create
<percorso_installazione_xampp>\xampp\php\php bin\console doctrine:schema:create

### SISTEMI LINUX
<percorso_installazione_xampp>/lampp/bin/php bin/console doctrine:database:create
<percorso_installazione_xampp>/lampp/bin/php bin/console doctrine:schema:create
```

A questo punto, inserire i dati iniziali del sistema, eseguendo il seguente comando
dalla finestra di terminale:
```
### SISTEMI WINDOWS
<percorso_installazione_xampp>\xampp\php\php bin\console doctrine:database:import dati_iniziali.sql

### SISTEMI LINUX
<percorso_installazione_xampp>/lampp/bin/php bin/console doctrine:database:import dati_iniziali.sql
```


### 4. Utilizzo dell'applicazione giua@school

Per provare l'applicazione è conveniente usare il server di sviluppo di Symfony al posto di *Apache*.

Per prima cosa, tramite il *Control Panel* di XAMPP, assicurarsi che sia attivo il database server *MySQL/MariaDB*.

Dalla finestra di terminale, quindi, inserire il seguente comando:
```
### SISTEMI WINDOWS
<percorso_installazione_xampp>\xampp\php\php bin\console server:run

### SISTEMI LINUX
<percorso_installazione_xampp>/lampp/bin/php bin/console server:run
```

Una volta eseguito il comando, non chiudere la finestra di terminale e aprire il browser,
andando all'indirizzo: [http://127.0.0.1:8000](http://127.0.0.1:8000)

Per poter provare l'applicazione, sono stati configurati alcuni utenti:
  - **admin**: amministratore di sistema
  - **preside**: preside
  - **docente1**: docente di Informatica (fa parte dei collaboratori del dirigente)
  - **docente2**: docente di Lettere
  - **docente3**: ITP di Informatica
  - **docente4**: docente di Matematica
  - **docente5**: docente di Sostegno
  - **alunno1.f1**: genitore di un alunno con sostegno
  - **alunno2.f1**: genitore di un alunno
  - **alunno3.f1**: genitore di un alunno

La password è uguale per tutti gli utenti ed è la seguente:
  - **12345678**

Per terminare l'applicazione: chiudere la finestra di terminale, quindi disattivare il
database server *MySQL/MariaDB* usando il *Control Panel* di XAMPP.


## CREDITS

Si desidera ringraziare per il loro importante contributo, tutti i membri della comunità dell'Open Source, e in particolare i creatori di:
- Lampschool
- Symfony
- Doctrine
- Twig
- Jquery
- Boostrap
- Apache
- Mysql/MariaDB
- Debian
