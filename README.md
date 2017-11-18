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


### 1. Installare il web server e il database server

Scaricare [XAMPP](https://www.apachefriends.org/it/download.html), facendo
attenzione a scegliere la versione per il proprio sistema operativo che
includa il **PHP 5.x** (non quelle con il *PHP 7.x*).

Installare XAMPP sul proprio computer; in caso di difficoltà consultare la
sezione delle FAQ presente sul loro sito.

È sufficente installare i seguenti componenti:
  - Apache
  - MySQL/MariaDB
  - PHP


### 2. Scaricare giua@school

Scaricare sul proprio computer l'ultima versione disponibile di giua@school.
Estrarre il file compresso nella directory usata da XAMPP per la
visualizzazione dei siti web, solitamente:




## CREDITS
