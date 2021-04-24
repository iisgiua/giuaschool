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
  - [Jquery](https://jquery.com/), [Bootstrap](https://getbootstrap.com/)
    e [Bootstrap Italia](https://italia.github.io/bootstrap-italia/) per l'interfaccia grafica.


## REQUISITI DI SISTEMA

I requisiti minimi per l'installazione sono:
  - web server **Apache 2.x** o superiore;
  - database server **MySQL 5.5** o superiore, o versioni equivalenti di **MariaDB**;
  - **PHP 7.4**;
  - framework **Symfony 4.4**.

Ci sono ulteriori requisiti minori che sono richiesti dal framework **Symfony**.


## INSTALLAZIONE DI PROVA

### 1. Uso dei docker

Per provare *giua@school* si consiglia l'installazione in locale, tramite l'uso di un contenitore **docker**
([cosa sono i docker?](https://it.wikipedia.org/wiki/Docker)).
L'uso dei **docker** semplifica notevolmente la gestione delle dipendenze richieste dai diversi componenti
dell'applicazione, creando un ambiente virtuale in cui eseguire l'installazione completa di tutto
quanto necessario.

Se non è già presente la gestione dei **docker** nel proprio computer, è necessario procedere alla sua installazione:
  - [installazione per Windows](https://docs.docker.com/docker-for-windows/install/)
  - [installazione per MacOs](https://docs.docker.com/docker-for-mac/install/)
  - [installazione per Linux Ubuntu](https://docs.docker.com/engine/install/ubuntu/)
  - [installazione per Linux Debian](https://docs.docker.com/engine/install/debian/)

Esistono in rete diverse guide in italiano che forniscono maggiori dettagli sull'installazion e sull'uso dei **docker**,
come, ad esempio, quella di [HTML.IT](https://www.html.it/guide/docker/).

### 2. Avvio del server

Il comando seguente scarica l'immagine dell'applicazione ed avvia il server in un contenitore **docker**:
```docker run -d --name gs_test -p 80:80 ghcr.io/trinko/giuaschool:latest

L'immagine verrà scaricata dal repository di **GitHub**, ma se si preferisce usare **Docker Hub**, allora
si può modificare il comando nel modo seguente:
```docker run -d --name gs_test -p 80:80 trinkodok/giuaschool:latest

Nel caso il comando riporti un errore di rete del tipo
**"listen tcp4 0.0.0.0:80: bind: address already in use"**,
significa che la porta 80 è già utilizzata da un altro servizio del proprio computer.
Si può quindi impostare una porta differente, ad esempio 8080, modificando il comando come indicato di seguito:
```docker run -d --name gs_test -p 8080:80 ghcr.io/trinko/giuaschool:latest

### 3. Uso dell'applicazione

Una volta avviato il server, usare l'indirizzo seguente nel proprio browser per visualizzare la pagina di accesso:
  - [http://localhost](http://localhost)

Nel caso sia stato modificato il numero di porta, è necessario specificarlo nell'indirizzo.
Ad esempio, se è stata impostata la porta 8080, l'indirizzo da utilizzare sarà:
  - [http://localhost:8080](http://localhost:8080)

Accedere all'applicazione utilizzando le seguenti credenziali per l'utente amministratore:
  - nome utente: *admin*
  - password: *admin*

Se si desidera accedere all'applicazione con un altro utente, è necessario anzi tutto
visualizzare il nome utente del profilo desiderato: la password predefinita sarà identica al nome utente.
Si può, quindi, uscire dall'applicazione (pulsante ESCI in alto a destra) e effettuare l'accesso con le
credenziali del nuovo utente.
In alternativa, si può utilizzare la funzione Alias (menu SISTEMA -> ALIAS), che
permette all'amministratore di impersonare un altro utente, senza necessità di inserire password.

### 4. Chiusura del server

Per chiudere il server e liberare le risorse occupate, eseguire i comandi seguenti:
``` docker container stop gs_test
``` docker container rm gs_test


## INSTALLAZIONE IN UN SERVER DI PRODUZIONE

Per installare l'applicazione in un server di produzione, seguire i seguenti passi:
  - installare i software necessari indicati nella sezione dei REQUISITI DI SISTEMA;
  - installare **Symfony** attraverso l'uso di **Composer**;
  - creare il database attraverso gli appositi comandi della console di **Symfony**;
  - inserire i dati iniziali attraverso l'uso delle Fixtures.

**Si consiglia di seguire i passi utilizzati per la creazione dell'immagine del docker, presenti
nel file "docker/Dockerfile", adattando i comandi a quelli del proprio sistema operativo.**


## CREDITS

Si desidera ringraziare, per il loro importante contributo, tutti i membri della comunità dell'**Open Source**, e
in particolare gli sviluppatori coinvolti nei seguenti progetti:
- [Lampschool](http://www.lampschool.it/)
- [Symfony](https://symfony.com/)
- [Doctrine](http://www.doctrine-project.org/)
- [Twig](https://twig.symfony.com/)
- [Jquery](https://jquery.com/)
- [Bootstrap](https://getbootstrap.com/)
- [Bootstrap Italia](https://italia.github.io/bootstrap-italia/)
