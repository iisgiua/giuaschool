<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests;

use App\Entity\Alunno;
use Faker\Generator;
use Faker\Provider\it_IT\Person;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


/**
 * PersonaProvider - creazione dati di persone fittizie
 *
 * @author Antonello Dessì
 */
class PersonaProvider extends Person {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Lista di nomi maschili per il generatore di nomi fittizi
   *
   * @var array $nomeMaschile Lista di nomi maschili
   */
  protected static array $nomeMaschile = [
    'Abbondio', 'Abramo', 'Achille', 'Adolfo', 'Adone', 'Adriano', 'Agostino', 'Alan', 'Alberto', 'Albino', 'Aldo',
      'Alessandro', 'Alessio', 'Alfio', 'Alfredo', 'Alighiero', 'Alvaro', 'Ambrogio', 'Amedeo', 'Amerigo', 'Amilcare',
      'Amos', 'Anastasio', 'Andrea', 'Andreas', 'Angelo', 'Anselmo', 'Antimo', 'Antonello', 'Antonino', 'Antonio',
      'Arcibaldo', 'Arduino', 'Aristide', 'Armando', 'Arnaldo', 'Aroldo', 'Arturo', 'Attilio', 'Audenico', 'Augusto',
      'Aurelio', 'Ausonio', '	Azelio',
    'Bacchisio', 'Baldassarre', 'Bartolo', 'Bartolomeo', 'Battista', 'Benedetto', 'Beniamino', 'Benigno', 'Benvenuto',
      'Benito', 'Bernardo', 'Bettino', 'Biagio', 'Boris', 'Bortolo', 'Brando', 'Bruno',
    'Caio', 'Caligola', 'Calogero', 'Camillo', 'Carlo', 'Carmelo', 'Carmine', 'Cecco', 'Cesare', 'Cirino', 'Ciro',
      'Claudio', 'Clemente', 'Corrado', 'Cosimo', 'Costante', 'Costantino', 'Costanzo', 'Cristiano',
    'Damiano', 'Daniele', 'Danilo', 'Danny', 'Dante', 'Dario', 'Davide', 'Demetrio', 'Diego', 'Dimitri', 'Dindo',
      'Dino', 'Domenico', 'Domingo', 'Domiziano', 'Donato', 'Duilio', 'Durante', 'Dylan',
    'Edilio', 'Edipo', 'Edoardo', 'Egidio', 'Egisto', 'Elia', 'Elio', 'Eliziario', 'Emanuel', 'Emanuele', 'Emidio',
      'Emiliano', 'Emilio', 'Enrico', 'Enzo', 'Ercole', 'Eriberto', 'Ermanno', 'Ermes', 'Erminio', 'Ernesto', 'Ethan',
      'Ettore', 'Eugenio', 'Eusebio', 'Eustachio', 'Evangelista', 'Ezio',
    'Fabiano', 'Fabio', 'Fabrizio', 'Fausto', 'Federico', 'Felice', 'Ferdinando', 'Fernando', 'Filippo', 'Fiorentino',
      'Fiorenzo', 'Flavio', 'Folco', 'Fortunato', 'Francesco', 'Fulvio', 'Furio',
    'Gabriele', 'Gaetano', 'Gaspare', 'Gastone', 'Gavino', 'Gennaro', 'Gerardo', 'Gerlando', 'Germano', 'Giacinto',
      'Giacobbe', 'Giacomo', 'Gianantonio', 'Giancarlo', 'Gianfranco', 'Gianleonardo', 'Gianluca', 'Gianluigi',
      'Gianmarco', 'Gianmaria', 'Gianni', 'Gianpaolo', 'Gianpiero', 'Gianpietro', 'Gianriccardo', 'Gilberto',
      'Gioacchino', 'Giobbe', 'Gioele', 'Giordano', 'Giorgio', 'Giosuè', 'Giovanni', 'Girolamo', 'Giuliano', 'Giulio',
      'Giuseppe', 'Graziano', 'Gregorio', 'Guglielmo', 'Guido',
    'Iacopo', 'Ian', 'Iginio', 'Ignazio', 'Ilario', 'Illuminato', 'Innocenzo', 'Ippolito', 'Isaia', 'Italo', 'Ivan',
      'Ivano', 'Ivo',
    'Jack', 'Jacopo', 'Jari', 'Jarno', 'Joannes', 'Joshua',
    'Karim',
    'Laerte', 'Lamberto', 'Lanfranco', 'Lapo', 'Lauro', 'Lazzaro', 'Leonardo', 'Libero', 'Liberato', 'Liborio', 'Lino',
      'Livio', 'Lorenzo', 'Loris', 'Luca', 'Luciano', 'Lucio', 'Ludovico', 'Luigi',
    'Maggiore', 'Manfredi', 'Manuele', 'Marcello', 'Marco', 'Mariano', 'Marino', 'Mario', 'Martino', 'Marvin', 'Marzio',
      'Massimiliano', 'Massimo', 'Matteo', 'Mattia', 'Maurizio', 'Mauro', 'Michael', 'Michelangelo', 'Michele', 'Mirco',
      'Mirko', 'Modesto', 'Moreno', 'Muzio',
    'Narciso', 'Natale', 'Nathan', 'Nazzareno', 'Nestore', 'Nick', 'Nico', 'Nino', 'Nicola', 'Nicolò', 'Norberto',
      'Nunzio',
    'Odino', 'Odone', 'Olindo', 'Omar', 'Onesto', 'Orazio', 'Oreste', 'Orfeo', 'Orlando', 'Oscar', 'Osvaldo', 'Ottavio',
    'Pablo', 'Pacifico', 'Paolo', 'Pasquale', 'Patrizio', 'Pericle', 'Pierangelo', 'Piererminio', 'Pierfrancesco',
      'Piergiorgio', 'Pierluigi', 'Piero', 'Piersilvio', 'Pietro', 'Priamo', 'Primo',
    'Quarto', 'Quasimodo', 	'Quintilio', 'Quirino',
    'Radames', 'Radio', 'Raffaele', 'Raimondo', 'Raniero', 'Raoul', 'Remo', 'Renato', 'Renzo', 'Riccardo', 'Roberto',
      'Rocco', 'Rodolfo', 'Rolando', 'Romano', 'Romeo', 'Romolo', 'Rosario', 'Rosolino', 'Rudy', 'Ruggero',
    'Sabatino', 'Sabino', 'Salvatore', 'Salvo', 'Samuel', 'Samuele', 'Sandro', 'Santo', 'Saverio', 'Savino', 'Sebastian',
      'Sebastiano', 'Secondo', 'Sergio', 'Serse', 'Sesto', 'Silvano', 'Silverio', 'Silvio', 'Simone', 'Sirio', 'Siro',
      'Stefano',
    'Tancredi', 'Tazio', 'Temistocle', 'Teodoro', 'Terzo', 'Teseo', 'Timoteo', 'Timothy', 'Tiziano', 'Tolomeo', 'Tommaso',
      'Trevis', 'Tristano', 'Tullio',
    'Ubaldo', 'Ugo', 'Ulrico', 'Umberto', 'Ultimo',
    'Valdo', 'Valente', 'Valentino', 'Valerio', 'Valter', 'Vincenzo', 'Vinicio', 'Virgilio', 'Virginio', 'Vito', 'Vittorio',
    'Walter',
    'Xavier',
    'Yago',
    'Zaccaria', 'Zeno'];

  /**
   * Lista di nomi femminili per il generatore di nomi fittizi
   *
   * @var array $nomeFemminile Lista di nomi femminili
   */
  protected static array $nomeFemminile = [
    'Alessia', 'Algisa', 'Alice', 'Alida', 'Allegra', 'Alma', 'Altea', 'Amalia', 'Amanda', 'Ambra', 'Ambrosia', 'Amelia',
      'Amina', 'Anastasia', 'Andreina', 'Antonia', 'Antonicca', 'Aquilina', 'Arcangela', 'Aria', 'Arianna', 'Armida',
      'Artemisia', 'Asia', 'Asmara', 'Assunta', 'Astrid', 'Augusta', 'Aura',
    'Barbara', 'Basilia', 'Beatrice', 'Benedetta', 'Bianca', 'Bonaria', 'Bonella', 'Brenda', 'Brigida', 'Brigitta',
      'Bruna', 'Brunilde',
    'Camilla', 'Candida', 'Carla', 'Carlotta', 'Carmela', 'Carmen', 'Carolina', 'Cassandra', 'Cassiopea', 'Caterina',
      'Catia', 'Cecilia', 'Celeste', 'Cesira', 'Chantal', 'Chiara', 'Cinzia', 'Clara', 'Clarissa', 'Claudia', 'Clelia',
      'Clorinda', 'Clotilde', 'Colomba', 'Concetta', 'Consolata', 'Consuelo',
    'Dalia', 'Dalila', 'Damiana', 'Daniela', 'Danila', 'Deborah', 'Delfina', 'Delia', 'Demetra', 'Denise', 'Desdemona',
      'Desideria', 'Diamante', 'Diana', 'Domitilla', 'Domiziana', 'Donatella', 'Dora', 'Doralice', 'Dorella', 'Doriana',
      'Doris', 'Dorotea',
    'Ebe', 'Edgarda', 'Edna', 'Edvige', 'Elena', 'Eleonora', 'Elettra', 'Eliana', 'Elide', 'Elisa', 'Elisabetta',
      'Eloisa', 'Elsa', 'Elvira', 'Emanuela', 'Emerenziana', 'Emilia', 'Emma', 'Enrica', 'Enrichetta', 'Eralda',
    'Fabiola', 'Fabrizia', 'Fatima', 'Fausta', 'Federica', 'Fedora', 'Fedra', 'Fernanda', 'Filippa', 'Flavia',
      'Florinda', 'Fortunata', 'Fosca', 'Franca', 'Francesca', 'Frida', 'Fulvia',
    'Gigliola', 'Gilda', 'Gina', 'Ginevra', 'Gioia', 'Giordana', 'Giorgia', 'Giovanna', 'Giovita', 'Gisella', 'Giuditta',
      'Giulia', 'Giuliana', 'Giulietta', 'Greta', 'Guendalina', 'Guia',
    'Hilary',
    'Iara', 'Ida', 'Ifigenia', 'Ilaria', 'Ileana', 'Ilenia', 'Immacolata', 'India', 'Ines', 'Ingrid', 'Iolanda',
      'Ivana', 'Ivonne',
    'Jasmine', 'Jessica', 'Jolanda', 'Jole',
    'Katia', 'Katiuscia', 'Krizia',
    'Laila', 'Lara', 'Larissa', 'Laura', 'Lavinia', 'Lea', 'Leila', 'Lella', 'Leondina', 'Leonilda', 'Letizia', 'Lia',
      'Liala', 'Libera', 'Loredana', 'Lorella', 'Lorena', 'Loretta', 'Loriana', 'Lorita', 'Luana', 'Luce', 'Lucetta',
      'Lucia', 'Luciana', 'Lucilla', 'Lucrezia', 'Ludovica',
    'Marcella', 'Marea', 'Mareta', 'Margherita', 'Maria', 'Mariagrazia', 'Marilena', 'Marilù', 'Marina', 'Marinella',
      'Mariolina', 'Marisa', 'Marisol', 'Maristella', 'Marta', 'Martina', 'Maruska', 'Marzia', 'Matilda', 'Matilde',
      'Michela', 'Mietta', 'Mila', 'Milena', 'Milva', 'Milvia', 'Mina', 'Miranda', 'Mirella', 'Miriam', 'Miriana',
      'Mirta', 'Mirzia', 'Moana', 'Moira', 'Monica',
    'Nadia', 'Natalina', 'Natascia', 'Nausica', 'Nayade', 'Nerina', 'Nicoletta', 'Nilde', 'Nilla', 'Nina', 'Ninfa',
      'Nives', 'Noemi', 'Nora', 'Norma', 'Nuccia', 'Nunzia',
    'Ofelia', 'Olga', 'Olimpia', 'Olivia', 'Ombretta', 'Onesta', 'Onorata', 'Oriana', 'Oriella', 'Orietta', 'Ornella',
      'Orsola', 'Ortensia', 'Ottavia',
    'Palma','Palmira', 'Pamela', 'Paola', 'Patrizia', 'Penelope', 'Perla', 'Petra', 'Pia', 'Piccarda', 'Piera',
      'Pierangela', 'Pina', 'Porzia', 'Priscilla', 'Provvidenza', 'Prudenzia',
    'Quieta', 'Quintina', 'Quinzia',
    'Rachele', 'Raffaella', 'Raissa', 'Ramona', 'Rebecca', 'Redenta', 'Regina', 'Renata', 'Rina', 'Rita', 'Roberta',
      'Romana', 'Romina', 'Rosa', 'Rosalba', 'Rosalia', 'Rosalinda', 'Rosangela', 'Rosanna', 'Rosetta',
    'Salome', 'Samanta', 'Samira', 'Sandra', 'Santa', 'Sara', 'Sarita', 'Sasha', 'Saviana', 'Sebastiana', 'Selene',
      'Selvaggia', 'Serafina', 'Serena', 'Severina', 'Sibilla', 'Silvana', 'Silvia', 'Simona', 'Siria', 'Smeralda',
      'Soave', 'Sofia', 'Sonia', 'Speranza', 'Stefania', 'Stella', 'Susanna', 'Sveva',
    'Tea', 'Tecla', 'Tilde', 'Tina', 'Tiziana', 'Tommasina', 'Tonia', 'Tosca', 'Tristana', 'Tullia',
    'Ubalda', 'Umberta', 'Ursula',
    'Valentina', 'Valeria', 'Vanda', 'Vanessa', 'Vincenza', 'Viola', 'Violetta', 'Virginia', 'Virna', 'Vita', 'Vitalba',
      'Vitalia', 'Vittoria', 'Viviana',
    'Wanda', 'Wendy', 'Wilma',
    'Yara', 'Yasmine', 'Ylenia', 'Yvonne',
    'Zaira', 'Zelda', 'Zelinda', 'Zita', 'Zoe'];

  /**
   * Lista di cognomi per il generatore di nomi fittizi
   *
   * @var array $cognome Lista di cognomi
   */
  protected static array $cognome = [
    'Abate', 'Abbagnato', 'Abis', 'Acardi', 'Agnini', 'Agostini', 'Aiello', 'Albanese', 'Amato', 'Andreoli', 'Angelini',
      'Antonini', 'Aragone', 'Arbore', 'Arena', 'Arrivabene', 'Artusi', 'Asmodei', 'Azzalin', 'Azzoni',
    'Baldi', 'Baldini', 'Barbieri', 'Barone', 'Baroni', 'Basile', 'Basso', 'Battaglia', 'Belli', 'Bellini', 'Benedetti',
      'Berardi', 'Beretta', 'Bernardi', 'Bernardini', 'Berti', 'Bevilacqua', 'Bianchi', 'Bianchini', 'Bianco', 'Biondi',
      'Bonetti', 'Bosco', 'Brunetti', 'Bruni', 'Bruno', 'Bucci',
    'Caccialupi Olivieri', 'Calabrese', 'Campana', 'Caputo', 'Carbone', 'Carboneris Morettini', 'Carboni', 'Carta',
      'Caruso', 'Casella', 'Catalano', 'Cattaneo', 'Cavallaro', 'Cavalli', 'Cavallo', 'Chiesa', 'Cipriani', 'Cirillo',
      'Cocco', 'Colombo', 'Compagnucci Spagnoli', 'Conte', 'Conti', 'Coppola', 'Cortese', 'Corti', 'Cosentino', 'Costa',
      'Costantini', 'Costantino', 'Costanzo', 'Cristofanelli Broglio', 'Cristofanelli Rainaldi',
    'D\'Alessandro', 'D\'Ambrosio', 'D\'Andrea', 'D\'Amico', 'D\'Angelo', 'Damico', 'De Angelis', 'De Luca', 'De Marco',
      'De Rosa', 'De Santis', 'De Simone', 'Di Benedetto', 'Di Lorenzo', 'Di Marco', 'Di Martino', 'Di Pietro', 'Di Stefano',
      'Difrancescantonio', 'Donati',
    'Eletti', 'Enas', 'Esposito', 'Este', 'Esu', 'Etzi',
    'Fabbri', 'Falcone', 'Farina', 'Fava', 'Ferrante', 'Ferrara', 'Ferrari', 'Ferraro', 'Ferretti', 'Ferri', 'Filippi',
      'Fiore', 'Fiorini', 'Fontana', 'Forte', 'Fortunato', 'Franceschini', 'Franchi', 'Fusco',
    'Gagliardi', 'Galli', 'Gallo', 'Garofalo', 'Gasparini', 'Gatti', 'Gatto', 'Genovese', 'Gentile', 'Giannini', 'Giglio',
      'Giordano', 'Giorgi', 'Girardi', 'Giuliani', 'Giuliano', 'Giusti', 'Grandi', 'Grassi', 'Grasso', 'Graziano',
      'Greco', 'Grillo', 'Grimaldi', 'Grossi', 'Grosso', 'Guarino', 'Guerra', 'Guida', 'Guidi',
    'Lancia', 'Landi', 'Lanza', 'Lazzari', 'Leonardi', 'Leone', 'Leoni', 'Locatelli', 'Lombardi', 'Lombardo', 'Longo',
    'Macrì', 'Maggi', 'Magnani', 'Manca', 'Mancini', 'Mancuso', 'Manfredi', 'Mantovani', 'Marchese', 'Marchesi',
      'Marchetti', 'Marchi', 'Marconi', 'Mariani', 'Marinelli', 'Marini', 'Marino', 'Mariotti', 'Marra', 'Martelli',
      'Martinelli', 'Martini', 'Martino', 'Massa', 'Mauro', 'Mazza', 'Mazzola', 'Mazzoni', 'Mele', 'Meloni', 'Merlo',
      'Messina', 'Milani', 'Monaco', 'Montanari', 'Monti', 'Morelli', 'Moretti', 'Morettini Paracucchi', 'Mori',
      'Moroni', 'Mosca', 'Motta',
    'Nadi', 'Napoli', 'Napolitano', 'Nardi', 'Nasi', 'Natale', 'Nazzari', 'Negri', 'Neri', 'Niccandri', 'Nisi',
    'Oddoni', 'Oliva', 'Olivieri Parteguelfa', 'Onnis', 'Oppo', 'Orlandi', 'Orlando', 'Orrù',
    'Pace', 'Pagani', 'Pagano', 'Palermo', 'Palma', 'Palmieri', 'Palumbo', 'Papa', 'Parisi', 'Pasquadibisceglia',
      'Pasquali', 'Pastore', 'Pavan', 'Pellegrini', 'Pellegrino', 'Pepe', 'Perrone', 'Pesce', 'Piccolo', 'Pini', 'Pinna',
      'Pinto', 'Piras', 'Pisani', 'Pisano', 'Piva', 'Pozzi', 'Pugliese',
    'Quaglia', 'Quadrato', 'Quarantotto', 'Quintavalle', 'Quondamangelomaria',
    'Raimondi', 'Rainaldi Broglio', 'Reboni', 'Riccardi', 'Ricci', 'Ricciardi', 'Rinaldi', 'Riva', 'Rizzi', 'Rizzo',
      'Rocca', 'Rocco', 'Romagnoli', 'Romano', 'Romeo', 'Rosati', 'Rossetti', 'Rossi', 'Rossini', 'Rosso', 'Rota',
      'Rubino', 'Ruggiero', 'Russo',
    'Sacchi', 'Sacco', 'Sagramoso Polfranceschi', 'Sala', 'Salerno', 'Salvi', 'Sanna', 'Santi', 'Santini', 'Santoro',
      'Sartori', 'Scotti', 'Serafini', 'Serra', 'Silvestri', 'Simone', 'Simonetti', 'Sorrentino', 'Spada', 'Spina',
      'Stefani', 'Stella',
    'Tabusi', 'Tagliabue', 'Tagliati', 'Tedeschi', 'Tedesco', 'Testa', 'Tolomei', 'Tomassoni Compagnucci', 'Tomba',
      'Tosi', 'Trevisan', 'Trinchi', 'Trinchero',
    'Ubaldi', 'Ugolotti', 'Uzzi',
    'Vaccaro', 'Valenti', 'Valentini', 'Ventura', 'Venturini', 'Viganò', 'Villa', 'Villani', 'Vitale', 'Vitali',
      'Volpe', 'Volpi',
    'Zaccarini', 'Zacchi', 'Zambon Polfranceschi', 'Zambon Sagramoso', 'Zamparo', 'Zanella', 'Zanetti', 'Zanini'];

  /**
   * Lista dei formati per il generatore di numeri di telefono fittizi
   *
   * @var array $formatoTelefono Lista di formati
   */
  protected static array $formatoTelefono = [
    '0## ### ###',
    '3## ### ###',
    '+39 0## ### ###',
    '+39 3## ### ###'
  ];

  /**
   * Lista dei dati dell'utente generato (dati memorizzati per chiamate separate)
   *
   * @var array $datiUtente Lista dei dati dell'utente generato
   */
  protected static array $datiUtente = [];

  /**
   * Servizio per la codifica delle password
   *
   * @var UserPasswordHasherInterface $hasher Gestore della codifica delle password
   */
  protected UserPasswordHasherInterface $hasher;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @var Generator $generator Generatore automatico di dati fittizi
   * @var UserPasswordHasherInterface $hasher Gestore della codifica delle password
   */
  public function __construct(Generator $generator, UserPasswordHasherInterface $hasher) {
    parent::__construct($generator);
    $this->hasher = $hasher;
    static::$datiUtente = ['nome' => '', 'cognome' => '', 'username' => '', 'password' => ''];
  }

  /**
   * Genera un nome fittizio (maschile o femminile)
   *
   * @param string $genere Indica il genere del nome fittizio da generare (M=maschile, F=femminile)
   *
   * @return string Il nome fittizio generato
   */
  public function nome(string $genere=null): string {
    if (empty($genere)) {
      $genere = static::randomElement(['M', 'F']);
    }
    if ($genere == 'M') {
      // maschile
      return static::nomeMaschile();
    } else {
      // femminile
      return static::nomeFemminile();
    }
  }

  /**
   * Genera un nome fittizio maschile
   *
   * @return string Il nome fittizio generato
   */
  public function nomeMaschile(): string {
    // genera nome
    $nome = static::randomElement(static::$nomeMaschile);
    if ($this->numberBetween(0, 100) > 80) {
      // col 20% di probabilità genera un secondo nome
      $secondo = static::randomElement(static::$nomeMaschile);
      if ($nome != $secondo) {
        $nome .= ' '.$secondo;
      }
    }
    // restituisce nome
    return $nome;
  }

  /**
   * Genera un nome fittizio femminile
   *
   * @return string Il nome fittizio generato
   */
  public function nomeFemminile(): string {
    // genera nome
    $nome = static::randomElement(static::$nomeFemminile);
    if ($this->numberBetween(0, 100) > 80) {
      // col 20% di probabilità genera un secondo nome
      $secondo = static::randomElement(static::$nomeFemminile);
      if ($nome != $secondo) {
        $nome .= ' '.$secondo;
      }
    }
    // restituisce nome
    return $nome;
  }

  /**
   * Genera un cognome fittizio
   *
   * @return string Il cognome fittizio generato
   */
  public function cognome(): string {
    return static::randomElement(static::$cognome);
  }

  /**
   * Genera nome, cognome e username fittizi per un utente
   *
   * @param string $nome Nome dell'utente
   * @param string $cognome Cognome dell'utente
   *
   * @return string la username fittizia generata
   */
  public function nomeUtente(string $nome, string $cognome): string {
    $username = strtolower(str_replace([' ','\'','à','è','é','ì','ò','ù'], ['','','a','e','e','i','o','u'], $nome).
      '.'.str_replace([' ','\'','à','è','é','ì','ò','ù'], ['','','a','e','e','i','o','u'], $cognome));
    // restituisce la username generata
    return $username;
  }

  /**
   * Genera una password codificata a partire da una in chiaro
   *
   * @param string $password Password da codificare
   *
   * @return string Password codificata
   */
  public function passwordCodificata(string $password): string {
    $passwordCodificata = $this->hasher->hashPassword(new \App\Entity\Utente(), $password);
    // restituisce la password codificata
    return $passwordCodificata;
  }

  /**
   * Genera e memorizza i dati di un utente, permettendo di recuperare i valori con chiamate distinte
   *
   * @param string $genere Indica il sesso dell'utente (M=maschile, F=femminile)
   * @param string $suffisso Testo aggiunto in coda alla username
   *
   * @return string La username generata
   */
  public function generaUtente(string $genere, string $suffisso=''): string {
    // genera nuovi dati utente univoci
    $nome = $this->nome($genere);
    $cognome = $this->cognome();
    $username = $this->nomeUtente($nome, $cognome).$suffisso;
    $password = $this->passwordCodificata($username);
    // memorizza dati
    static::$datiUtente['nome'] = $nome;
    static::$datiUtente['cognome'] = $cognome;
    static::$datiUtente['username'] = $username;
    static::$datiUtente['password'] = $password;
    // restituisce la username
    return $username;
  }

  /**
   * Restituisce il dato dell'utente generato in precedenza
   *
   * @param string $dato Indica il dato da restituire tra quelli generati [nome, cognome, username, password]
   *
   * @return string Il dato indicato
   */
  public function datoUtente(string $dato): string {
    return static::$datiUtente[$dato];
  }

  /**
   * Genera numeri di telefono fittizi
   *
   * @param int $max Numero massimo di recapiti telefonici da generare
   *
   * @return array Lista di numeri di telefono fittizi
   */
  public function telefono(int $max): array {
    $tel = [];
    $num = static::numberBetween(0, $max);
    for ($i = 0; $i < $num; $i++) {
      $tel[] = static::numerify($this->generator->parse(static::randomElement(static::$formatoTelefono)));
    }
    // restituisce i dati generati
    return $tel;
  }

  /**
   * Restituisce un voto casuale (0-10)
   *
   * @return int Voto generato
   */
  public function voto(): int {
    return static::numberBetween(0, 10);
  }

  /**
   * Restituisce un voto casuale di Ed.Civica (2-10)
   *
   * @return int Voto generato
   */
  public function votoEdCivica(): int {
    return static::numberBetween(2, 10);
  }

  /**
   * Restituisce un voto casuale di Ed.Civica, escluso NC (3-10)
   *
   * @return int Voto generato
   */
  public function votoEdCivicaNoNC(): int {
    return static::numberBetween(3, 10);
  }

  /**
   * Restituisce un voto casuale di Condotta (4-10)
   *
   * @return int Voto generato
   */
  public function votoCondotta(): int {
    return static::numberBetween(4, 10);
  }

  /**
   * Restituisce un voto casuale di Condotta, escluso NC (5-10)
   *
   * @return int Voto generato
   */
  public function votoCondottaNoNC(): int {
    return static::numberBetween(5, 10);
  }

  /**
   * Restituisce un voto casuale di religione (20-27)
   *
   * @param Alunno $alunno Istanza dell'alunno
   *
   * @return int|null Voto generato
   */
  public function votoReligione(Alunno $alunno): ?int {
    return in_array($alunno->getReligione(), ['S', 'A'], true) ? static::numberBetween(20, 27) : null;
  }

  /**
   * Restituisce una data creata da una stringa costante
   *
   * @param string $data Stringa data nel formato "gg/mm/aaaa"
   *
   * @return \DateTime Oggetto data
   */
  public function dataFissa(string $data): \DateTime {
    return \DateTime::createFromFormat('d/m/Y H:i:s', $data.' 00:00:00');
  }

  /**
   * Restituisce un orario creato da una stringa costante
   *
   * @param string $ora Stringa ora nel formato "hh:mm"
   *
   * @return \DateTime Oggetto data
   */
  public function oraFissa(string $ora): \DateTime {
    return \DateTime::createFromFormat('H:i:s', $ora.':00');
  }

}
