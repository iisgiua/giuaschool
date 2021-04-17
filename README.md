# giua@school
*il Registro Elettronico dell'Istituto di Istruzione Superiore "Michele Giua"*

[![Build](https://github.com/trinko/giuaschool/actions/workflows/build.yml/badge.svg?branch=master)](https://github.com/trinko/giuaschool/actions/workflows/build.yml)
[![Test](https://github.com/trinko/giuaschool/actions/workflows/test.yml/badge.svg?branch=master)](https://github.com/trinko/giuaschool/actions/workflows/test.yml)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/d1e4b6505b984dc190eb3e89e86868ff)](https://www.codacy.com/gh/trinko/giuaschool/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=trinko/giuaschool&amp;utm_campaign=Badge_Grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/d1e4b6505b984dc190eb3e89e86868ff)](https://www.codacy.com/gh/trinko/giuaschool/dashboard?utm_source=github.com&utm_medium=referral&utm_content=trinko/giuaschool&utm_campaign=Badge_Coverage)

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
  - [Jquery](https://jquery.com/) e [Bootstrap](https://getbootstrap.com/) per l'interfaccia grafica.


## REQUISITI DI SISTEMA

I requisiti minimi per l'installazione sono quelli richiesti da *Symfony 4.3*:
  - web server **Apache 2.x** o superiore;
  - database server **MySQL 5.5** o superiore, o versioni equivalenti di
    **MariaDB** (sono supportate anche altre piattaforme, ma non sono state
    testate con *giua@school*)
  - **PHP 7.1** o superiore;
  - ulteriori requisiti minori.

Per semplificare le cose, *Symfony* mette a disposizione uno strumento di
verifica dei requisiti, come meglio specificato nella sezione
dell'installazione.


## INSTALLAZIONE

Per provare *giua@school* si consiglia l'installazione in locale, sul proprio
computer, seguendo i passi descritti di seguito.


### 1. Installazione del web server e del database server

#### 1.1 Su Windows
Scaricare [XAMPP](https://www.apachefriends.org/it/download.html), facendo
attenzione a scegliere la versione per il proprio sistema operativo che
includa il **PHP 7.x**.

Installare XAMPP sul proprio computer; in caso di difficoltà consultare la
sezione delle FAQ presente sul loro sito.

È sufficente installare i seguenti componenti:
  - Apache
  - MySQL/MariaDB
  - PHP

Al termine dell'installazione avviare il server database **MySQL/MariaDB** usando il *Control Panel* di
XAMPP.

#### 1.2 Su Linux
Esistono molte guide sull'installazione di server LAMP (Apache, Mysql e Php su Linux).
In seguito è presente una breve guida riguardante l'installazione su Ubuntu (qualsiasi versione dalla 16.04).
Aprire una finestra di terminale ed eseguire il seguente comando.
```bash
sudo apt-get install apache2 mysql-server curl php7.3 php7.3-mysql php7.3-initl -y
```
A questo punto, dovrebbe partire l'installazione dei software necessari.
Verranno effettuate alcune domande, tra cui la password da utilizzare per MySql: scegliere una password e scriverla in un posto sicuro.
Alla fine dell'esecuzione del comando precedentemente indicato, verificare la corretta esecuzione del server web aprendo un broser e andando su `http://localhost/` oppure, utilizzando il terminale, scrivere il comando
```bash
curl --silent http://localhost -o /dev/null -w "%{http_code}"
```
Se viene visualizzata una pagina web (dal browser) o se viene visualizzato il numero 200 (da terminale), la prima parte dell'installazione è avvenuta con successo.
Aprire nuovamente il terminale per inserire gli ultimi comandi necessari all'installazione del server web.
```bash
printf "<IfModule mod_dir.c>\nDirectoryIndex index.php index.html index.htm index.cgi index.pl\n</IfModule>" | sudo tee /etc/apache2/mods-enabled/dir.conf
sudo systemctl restart apache2
sudo rm /var/www/html/index.*
echo "<?php echo('fun'); echo('ziona'); ?>" | sudo tee /var/www/html/index.php
curl --silent http://localhost
sudo rm /var/www/html/index.php
```
Se viene visualizzata la scritta "funziona" nel terminale, significa che il web server è stato installato con successo

### 2. Installazione di giua@school

Scaricare sul proprio computer l'ultima versione disponibile di *giua@school* dal sito https://github.com/trinko/giuaschool/releases/latest,
quindi estrarre i file nella cartella usata dal server web.
Normalmente il percorso di questa cartella è simile a quanto segue:
```
### SISTEMI WINDOWS
C:\xampp\htdocs

### SISTEMI LINUX
/var/www/html
```

**ATTENZIONE UTENTI LINUX:** Dopo aver copiato/scaricato i files nella cartella /var/www/html è necessario aprire il terminale e scrivere
```bash
sudo find /var/www/ -type d -exec chmod 755 {} \;
sudo find /var/www/ -type f -exec chmod 644 {} \;
sudo chown -R www-data:www-data /var/www/
```

Aprire una finestra di terminale (o *Prompt dei comandi* per
i sistemi Windows) e posizionarsi all'interno della cartella dove si sono
estratti i file di *giua@school*.
Ad esempio, il comando per posizionarsi nella cartella "giuaschool", dove si trovano i file
dell'applicazione, sarà il seguente:
```
### SISTEMI WINDOWS
cd <percorso_installazione_xampp>\xampp\htdocs\giuaschool

### SISTEMI LINUX
cd /var/www/html/giuaschool
```

Sempre dalla finestra di terminale, eseguire il seguente comando per verificare che i requisiti di sistema siano
corretti:
```
### SISTEMI WINDOWS
<percorso_installazione_xampp>\xampp\php\php bin\symfony_requirements

### SISTEMI LINUX
php bin/symfony_requirements
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

**ATTENZIONE:** se anziché una nuova installazione di XAMPP si è scelto di utilizzare
un'installazione pre-esistente di MySQL/MariaDB, sarà necessario
modificare i parametri di connessione al database, che si trovano nel file:
```
### SISTEMI WINDOWS
<percorso_installazione_giua-school>\.env

### SISTEMI LINUX
<percorso_installazione_giua-school>/.env
```


### 3. Creazione del database di giua@school

Per creare il database di *giua@school* eseguire i seguenti comandi dalla
finestra di terminale:
```
### SISTEMI WINDOWS
<percorso_installazione_xampp>\xampp\php\php bin\console doctrine:database:create
<percorso_installazione_xampp>\xampp\php\php bin\console doctrine:schema:create

### SISTEMI LINUX
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
```

A questo punto, inserire i dati iniziali del sistema, eseguendo il seguente comando
dalla finestra di terminale:
```
### SISTEMI WINDOWS
<percorso_installazione_xampp>\xampp\php\php bin\console doctrine:fixtures:load

### SISTEMI LINUX
php bin/console doctrine:fixtures:load
```


### 4. Utilizzo dell'applicazione giua@school

### 4.1 Avviare il database server
### SISTEMI WINDOWS
Per prima cosa, tramite il *Control Panel* di XAMPP, assicurarsi che sia attivo il database server *MySQL/MariaDB*.

### SISTEMI LINUX
Il database server dovrebbe essersi avviato automaticamente durante l'installazione. Per verificarne il funzionamento, aprire il terminale e scrivere (se si usa Ubuntu):
```bash
sudo service mysql status
```

### 4.2 Avviare il server Web di prova

Per provare l'applicazione è conveniente usare il server di sviluppo di Symfony al posto di *Apache*.

**ATTENZIONE:** il server di sviluppo **NON** deve essere usato normalmente per eseguire il registro sul server scolastico, dato che può contenere bug e vulnerabilità e che le performance sono ridotte. Una volta provato il funzionamento di giua@school è consigliabile l'utilizzo di Webserver veri e propri, come Apache o Nginx. Per ulteriori informazioni, leggi il paragrafo *4.3*.

Dalla finestra di terminale, inserire i seguente comandi:
```
### SISTEMI WINDOWS
cd <percorso_installazione_xampp>\xampp\htdocs\giuaschool
<percorso_installazione_xampp>\xampp\php\php bin\console server:run

### SISTEMI LINUX
cd /var/www/html/giuaschool
php bin/console server:run
```

Una volta eseguito il comando, senza chiudere la finestra di terminale, aprire il browser, 
andando all'indirizzo: [http://127.0.0.1:8000](http://127.0.0.1:8000)  
Chiudendo la finestra di terminale, verrà disattivato il server di prova.  

### 4.3 Avviare il server Web
Per eseguire giua@school in sicurezza e con ottime performance, è necessario utilizzare un Webserver come Apache o Nginx.  
Attualmente è consigliato l'uso di **Apache**, dato che non richiede configurazione ed è gia stato installato nello step 3. Per avviare Apache, seguire i seguenti passaggi:
- SISTEMI WINDOWS
  1. Aprire nuovamente *Control Panel* di XAMPP (usato precedentemente per avviare il server database)
  2. Premere su *start* nella riga di *Apache*
- SISTEMI LINUX
  1. Apache dovrebbe essersi avviato automaticamente durante l'installazione.

Per aumentare le preformance e rimuovere le opzioni di debug e sviluppo, aprire il file ```.env``` e modificare l'opzione ```APP_ENV=dev``` (seconda linea) in ```APP_ENV=prod```.  
Una volta avviato il web server, aprire il browser, 
andando all'indirizzo: [http://127.0.0.1](http://127.0.0.1)

Per poter provare l'applicazione, sono stati configurati alcuni utenti:
  - **admin**: amministratore di sistema
  - **preside**: preside

La password è uguale per tutti gli utenti ed è la seguente:
  - **12345678**

Per terminare l'applicazione:
### SISTEMI WINDOWS
Riaprire il *Control Panel* di XAMPP, e premere *stop* nelle righe di *MySQL/MariaDB* e di *Apache*.

### SISTEMI LINUX
Aprire il terminale e scrivere:
```bash
sudo systemctl stop apache
sudo systemctl disable apache
sudo systemctl stop mysql
sudo systemctl disable mysql
```

## CREDITS

Si desidera ringraziare, per il loro importante contributo, tutti i membri della comunità dell'Open Source, e in particolare gli sviluppatori coinvolti nei seguenti progetti:
- [Lampschool](http://www.lampschool.it/)
- [Symfony](https://symfony.com/)
- [Doctrine](http://www.doctrine-project.org/)
- [Twig](https://twig.symfony.com/)
- [Jquery](https://jquery.com/)
- [Bootstrap](https://getbootstrap.com/)


## DOWNLOAD

Scarica l'ultima versione disponibile:
- [giua@school](https://github.com/trinko/giua-school/releases/latest)
