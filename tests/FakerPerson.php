<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Tests;

use Faker\Provider\it_IT\Person;


/**
 * FakerPerson - creazione dati di persone fittizie
 */
class FakerPerson extends Person {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * Lista di nomi maschili per il generatore di nomi fittizi
   *
   * @var array $nomeMaschile Lista di nomi maschili
   */
  protected static $nomeMaschile = [
    'Abramo', 'Adolfo', 'Adriano', 'Agostino', 'Alan', 'Alberto', 'Albino', 'Aldo',
    'Alessandro', 'Alessio', 'Alfio', 'Alfredo', 'Alighiero', 'Alvaro', 'Ambrogio',
    'Amedeo', 'Amerigo', 'Amos', 'Anastasio', 'Andrea', 'Angelo', 'Anselmo',
    'Antimo', 'Antonello', 'Antonino', 'Antonio', 'Arcibaldo', 'Arduino', 'Armando',
    'Arnaldo', 'Aroldo', 'Arturo', 'Attilio', 'Audenico', 'Augusto', 'Aurelio',
    'Ausonio', 'Bacchisio', 'Baldassarre', 'Bartolomeo', 'Battista', 'Benedetto',
    'Beniamino', 'Bernardo', 'Bettino', 'Biagio', 'Boris', 'Bortolo', 'Bruno',
    'Caio', 'Caligola', 'Calogero', 'Camillo', 'Carlo', 'Carmelo', 'Carmine',
    'Cecco', 'Cesare', 'Cirino', 'Ciro', 'Claudio', 'Clemente', 'Corrado', 'Cosimo',
    'Costantino', 'Costanzo', 'Cristiano', 'Damiano', 'Daniele', 'Danilo', 'Danny',
    'Dante', 'Dario', 'Davide', 'Diego', 'Dimitri', 'Dindo', 'Dino', 'Domenico',
    'Domingo', 'Domiziano', 'Donato', 'Dylan', 'Edilio', 'Edipo', 'Edoardo',
    'Egidio', 'Egisto', 'Elia', 'Elio', 'Eliziario', 'Emanuel', 'Emanuele',
    'Emidio', 'Emiliano', 'Emilio', 'Enrico', 'Enzo', 'Ercole', 'Eriberto',
    'Ermanno', 'Ermes', 'Erminio', 'Ernesto', 'Ethan', 'Ettore', 'Eugenio',
    'Eusebio', 'Eustachio', 'Evangelista', 'Ezio', 'Fabiano', 'Fabio', 'Fabrizio',
    'Fausto', 'Federico', 'Felice', 'Ferdinando', 'Fernando', 'Filippo',
    'Fiorentino', 'Fiorenzo', 'Flavio', 'Folco', 'Fortunato', 'Francesco', 'Fulvio',
    'Furio', 'Gabriele', 'Gaetano', 'Gaspare', 'Gastone', 'Gavino', 'Gennaro',
    'Gerardo', 'Gerlando', 'Germano', 'Giacinto', 'Giacobbe', 'Giacomo',
    'Gianantonio', 'Giancarlo', 'Gianfranco', 'Gianleonardo', 'Gianluca',
    'Gianluigi', 'Gianmarco', 'Gianmaria', 'Gianni', 'Gianpaolo', 'Gianpiero',
    'Gianpietro', 'Gianriccardo', 'Gilberto', 'Gioacchino', 'Giobbe', 'Gioele',
    'Giordano', 'Giorgio', 'Giosuè', 'Giovanni', 'Girolamo', 'Giuliano', 'Giulio',
    'Giuseppe', 'Graziano', 'Gregorio', 'Guglielmo', 'Guido', 'Iacopo', 'Ian',
    'Ignazio', 'Ilario', 'Ippolito', 'Isira', 'Italo', 'Ivan', 'Ivano', 'Ivo', 'Jack',
    'Jacopo', 'Jari', 'Jarno', 'Joannes', 'Joshua', 'Karim', 'Laerte', 'Lamberto',
    'Lauro', 'Lazzaro', 'Leonardo', 'Liborio', 'Lino', 'Livio', 'Lorenzo', 'Loris',
    'Luca', 'Luciano', 'Lucio', 'Ludovico', 'Luigi', 'Maggiore', 'Manfredi',
    'Manuele', 'Marcello', 'Marco', 'Mariano', 'Marino', 'Mario', 'Martino',
    'Marvin', 'Marzio', 'Massimiliano', 'Massimo', 'Matteo', 'Mattia', 'Maurizio',
    'Mauro', 'Michael', 'Michelangelo', 'Michele', 'Mirco', 'Mirko', 'Modesto',
    'Moreno', 'Muzio', 'Natale', 'Nathan', 'Nazzareno', 'Nestore', 'Nick', 'Nico',
    'Nicola', 'Nicolò', 'Odino', 'Odone', 'Omar', 'Orazio', 'Oreste', 'Orfeo',
    'Orlando', 'Oscar', 'Osvaldo', 'Ottavio', 'Pablo', 'Pacifico', 'Paolo',
    'Pasquale', 'Patrizio', 'Pericle', 'Pierangelo', 'Piererminio', 'Pierfrancesco',
    'Piergiorgio', 'Pierluigi', 'Piero', 'Piersilvio', 'Pietro', 'Priamo', 'Primo',
    'Quarto', 'Quasimodo', 'Quirino', 'Radames', 'Radio', 'Raffaele', 'Raimondo',
    'Raniero', 'Raoul', 'Remo', 'Renato', 'Renzo', 'Riccardo', 'Roberto', 'Rocco',
    'Rodolfo', 'Rolando', 'Romano', 'Romeo', 'Romolo', 'Rosario', 'Rosolino',
    'Rudy', 'Ruggero', 'Sabatino', 'Sabino', 'Salvatore', 'Salvo', 'Samuel',
    'Samuele', 'Sandro', 'Santo', 'Saverio', 'Savino', 'Sebastian', 'Sebastiano',
    'Secondo', 'Sergio', 'Serse', 'Sesto', 'Silvano', 'Silverio', 'Silvio', 'Simone',
    'Sirio', 'Siro', 'Stefano', 'Tancredi', 'Tazio', 'Terzo', 'Teseo', 'Timoteo',
    'Timothy', 'Tiziano', 'Tolomeo', 'Tommaso', 'Trevis', 'Tristano', 'Ubaldo',
    'Ugo', 'Ulrico', 'Umberto', 'Valdo', 'Valentino', 'Valerio', 'Valter',
    'Vincenzo', 'Vinicio', 'Virgilio', 'Virginio', 'Vito', 'Vittorio', 'Walter',
    'Xavier', 'Yago', 'Zaccaria'];

  /**
   * Lista di nomi femminili per il generatore di nomi fittizi
   *
   * @var array $nomeFemminile Lista di nomi femminili
   */
  protected static $nomeFemminile = [
    'Alessia', 'Algisa', 'Alice', 'Alida', 'Allegra', 'Alma', 'Altea', 'Amalia',
    'Amanda', 'Ambra', 'Ambrosia', 'Amelia', 'Amina', 'Anastasia', 'Andreina',
    'Antonia', 'Antonicca', 'Aquilina', 'Arcangela', 'Aria', 'Arianna', 'Armida',
    'Artemisia', 'Asia', 'Asmara', 'Assunta', 'Astrid', 'Augusta', 'Aura',
    'Bonaria', 'Bonella', 'Brenda', 'Brigida', 'Brigitta', 'Bruna', 'Brunilde',
    'Camilla', 'Candida', 'Carla', 'Carlotta', 'Carmela', 'Carmen', 'Carolina',
    'Cassandra', 'Cassiopea', 'Caterina', 'Catia', 'Cecilia', 'Celeste', 'Cesira',
    'Chantal', 'Chiara', 'Cinzia', 'Clara', 'Clarissa', 'Claudia', 'Clelia',
    'Clorinda', 'Clotilde', 'Colomba', 'Concetta', 'Consolata', 'Consuelo',
    'Dalia', 'Dalila', 'Damiana', 'Daniela', 'Danila', 'Deborah', 'Delfina',
    'Delia', 'Demetra', 'Denise', 'Desdemona', 'Desideria', 'Diamante', 'Diana',
    'Domitilla', 'Domiziana', 'Donatella', 'Dora', 'Doralice', 'Dorella', 'Doriana',
    'Doris', 'Dorotea', 'Ebe', 'Edgarda', 'Edna', 'Edvige', 'Elena', 'Eleonora',
    'Elettra', 'Eliana', 'Elide', 'Elisa', 'Elisabetta', 'Eloisa', 'Elsa', 'Elvira',
    'Emanuela', 'Emerenziana', 'Emilia', 'Emma', 'Enrica', 'Enrichetta', 'Eralda',
    'Fabiola', 'Fabrizia', 'Fatima', 'Fausta', 'Federica', 'Fedora', 'Fedra',
    'Florinda', 'Fortunata', 'Fosca', 'Franca', 'Francesca', 'Frida', 'Fulvia',
    'Gigliola', 'Gilda', 'Gina', 'Ginevra', 'Gioia', 'Giordana', 'Giorgia',
    'Giovanna', 'Giovita', 'Gisella', 'Giuditta', 'Giulia', 'Giuliana', 'Giulietta',
    'Greta', 'Guendalina', 'Guia', 'Hilary', 'Iara', 'Ida', 'Ifigenia', 'Ilaria',
    'Ileana', 'Ilenia', 'Immacolata', 'India', 'Ines', 'Ingrid', 'Iolanda',
    'Ivana', 'Ivonne', 'Jasmine', 'Jessica', 'Jolanda', 'Jole', 'Katia',
    'Katiuscia', 'Krizia', 'Laila', 'Lara', 'Larissa', 'Laura', 'Lavinia', 'Lea',
    'Leila', 'Lella', 'Leondina', 'Leonilda', 'Letizia', 'Lia', 'Liala', 'Libera',
    'Loredana', 'Lorella', 'Lorena', 'Loretta', 'Loriana', 'Lorita', 'Luana',
    'Luce', 'Lucetta', 'Lucia', 'Luciana', 'Lucilla', 'Lucrezia', 'Ludovica',
    'Marcella', 'Marea', 'Mareta', 'Margherita', 'Maria', 'Mariagrazia',
    'Marilena', 'Marilù', 'Marina', 'Marinella', 'Mariolina', 'Marisa', 'Marisol',
    'Maristella', 'Marta', 'Martina', 'Maruska', 'Marzia', 'Matilda', 'Matilde',
    'Michela', 'Mietta', 'Mila', 'Milena', 'Milva', 'Milvia', 'Mina', 'Miranda',
    'Mirella', 'Miriam', 'Miriana', 'Mirta', 'Mirzia', 'Moana', 'Moira', 'Monica',
    'Natalina', 'Natascia', 'Nausica', 'Nayade', 'Nerina', 'Nicoletta', 'Nilde',
    'Nilla', 'Nina', 'Ninfa', 'Nives', 'Noemi', 'Nora', 'Norma', 'Nunzia', 'Ofelia',
    'Olga', 'Olimpia', 'Olivia', 'Ombretta', 'Onesta', 'Onorata', 'Oriana',
    'Oriella', 'Orietta', 'Ornella', 'Orsola', 'Ortensia', 'Ottavia', 'Palma',
    'Palmira', 'Pamela', 'Paola', 'Patrizia', 'Penelope', 'Perla', 'Petra', 'Pia',
    'Piccarda', 'Piera', 'Pierangela', 'Pina', 'Porzia', 'Priscilla', 'Provvidenza',
    'Quintina', 'Quinzia', 'Rachele', 'Raffaella', 'Raissa', 'Ramona', 'Rebecca',
    'Redenta', 'Regina', 'Renata', 'Rina', 'Rita', 'Roberta', 'Romana', 'Romina',
    'Rosa', 'Rosalba', 'Rosalia', 'Rosalinda', 'Rosangela', 'Rosanna', 'Rosetta',
    'Salome', 'Samanta', 'Samira', 'Sandra', 'Santa', 'Sara', 'Sarita', 'Sasha',
    'Saviana', 'Sebastiana', 'Selene', 'Selvaggia', 'Serafina', 'Serena',
    'Severina', 'Sibilla', 'Silvana', 'Silvia', 'Simona', 'Siria', 'Smeralda',
    'Soave', 'Sofia', 'Sonia', 'Speranza', 'Stefania', 'Stella', 'Susanna', 'Sveva',
    'Tilde', 'Tina', 'Tiziana', 'Tommasina', 'Tonia', 'Tosca', 'Tristana', 'Tullia',
    'Ubalda', 'Umberta', 'Ursula', 'Valentina', 'Valeria', 'Vanda', 'Vanessa',
    'Vincenza', 'Viola', 'Violetta', 'Virginia', 'Virna', 'Vita', 'Vitalba',
    'Vitalia', 'Vittoria', 'Viviana', 'Wendy', 'Wilma', 'Yara', 'Yasmine', 'Ylenia',
    'Yvonne', 'Zaira', 'Zelda', 'Zelinda', 'Zita', 'Zoe'];

  /**
   * Lista di cognomi per il generatore di nomi fittizi
   *
   * @var array $cognome Lista di cognomi
   */
  protected static $cognome = [
    'Abate', 'Agostini', 'Aiello', 'Albanese', 'Amato', 'Andreoli', 'Angelini',
    'Antonini', 'Arena', 'Baldi', 'Baldini', 'Barbieri', 'Barone', 'Baroni',
    'Basile', 'Basso', 'Battaglia', 'Belli', 'Bellini', 'Benedetti', 'Berardi',
    'Beretta', 'Bernardi', 'Bernardini', 'Berti', 'Bevilacqua', 'Bianchi',
    'Bianchini', 'Bianco', 'Biondi', 'Bonetti', 'Bosco', 'Brunetti', 'Bruni',
    'Bruno', 'Bucci', 'Caccialupi Olivieri', 'Calabrese', 'Campana', 'Caputo',
    'Carbone', 'Carboneris Morettini', 'Carboni', 'Carta', 'Caruso', 'Casella',
    'Catalano', 'Cattaneo', 'Cavallaro', 'Cavalli', 'Cavallo', 'Chiesa', 'Cipriani',
    'Cirillo', 'Cocco', 'Colombo', 'Compagnucci Spagnoli', 'Conte', 'Conti',
    'Coppola', 'Cortese', 'Corti', 'Cosentino', 'Costa', 'Costantini', 'Costantino',
    'Costanzo', 'Cristofanelli Broglio', 'Cristofanelli Rainaldi', 'D\'Alessandro',
    'D\'Ambrosio', 'D\'Andrea', 'D\'Amico', 'D\'Angelo', 'Damico', 'De Angelis', 'De Luca',
    'De Marco', 'De Rosa', 'De Santis', 'De Simone', 'Di Benedetto', 'Di Lorenzo',
    'Di Marco', 'Di Martino', 'Di Pietro', 'Di Stefano',
    'Difrancescantonio', 'Donati', 'Esposito', 'Fabbri', 'Falcone', 'Farina',
    'Fava', 'Ferrante', 'Ferrara', 'Ferrari', 'Ferraro', 'Ferretti', 'Ferri',
    'Filippi', 'Fiore', 'Fiorini', 'Fontana', 'Forte', 'Fortunato', 'Franceschini',
    'Franchi', 'Fusco', 'Gagliardi', 'Galli', 'Gallo', 'Garofalo', 'Gasparini',
    'Gatti', 'Gatto', 'Genovese', 'Gentile', 'Giannini', 'Giglio', 'Giordano',
    'Giorgi', 'Girardi', 'Giuliani', 'Giuliano', 'Giusti', 'Grandi', 'Grassi',
    'Grasso', 'Graziano', 'Greco', 'Grillo', 'Grimaldi', 'Grossi', 'Grosso',
    'Guarino', 'Guerra', 'Guida', 'Guidi', 'Lancia', 'Landi', 'Lanza', 'Lazzari',
    'Leonardi', 'Leone', 'Leoni', 'Locatelli', 'Lombardi', 'Lombardo', 'Longo',
    'Macrì', 'Maggi', 'Magnani', 'Manca', 'Mancini', 'Mancuso', 'Manfredi',
    'Mantovani', 'Marchese', 'Marchesi', 'Marchetti', 'Marchi', 'Marconi',
    'Mariani', 'Marinelli', 'Marini', 'Marino', 'Mariotti', 'Marra', 'Martelli',
    'Martinelli', 'Martini', 'Martino', 'Massa', 'Mauro', 'Mazza', 'Mazzola',
    'Mazzoni', 'Mele', 'Meloni', 'Merlo', 'Messina', 'Milani', 'Monaco',
    'Montanari', 'Monti', 'Morelli', 'Moretti', 'Morettini Paracucchi', 'Mori',
    'Moroni', 'Mosca', 'Motta', 'Napoli', 'Napolitano', 'Nardi', 'Natale', 'Negri',
    'Neri', 'Oliva', 'Olivieri Parteguelfa', 'Orlandi', 'Orlando', 'Orrù', 'Pace',
    'Pagani', 'Pagano', 'Palermo', 'Palma', 'Palmieri', 'Palumbo', 'Papa', 'Parisi',
    'Pasquadibisceglia', 'Pasquali', 'Pastore', 'Pavan', 'Pellegrini', 'Pellegrino',
    'Pepe', 'Perrone', 'Pesce', 'Piccolo', 'Pini', 'Pinna', 'Pinto', 'Piras',
    'Pisani', 'Pisano', 'Piva', 'Pozzi', 'Pugliese', 'Quondamangelomaria',
    'Raimondi', 'Rainaldi Broglio', 'Reboni', 'Riccardi', 'Ricci', 'Ricciardi',
    'Rinaldi', 'Riva', 'Rizzi', 'Rizzo', 'Rocca', 'Rocco', 'Romagnoli', 'Romano',
    'Romeo', 'Rosati', 'Rossetti', 'Rossi', 'Rossini', 'Rosso', 'Rota', 'Rubino',
    'Ruggiero', 'Russo', 'Sacchi', 'Sacco', 'Sagramoso Polfranceschi', 'Sala',
    'Salerno', 'Salvi', 'Sanna', 'Santi', 'Santini', 'Santoro', 'Sartori', 'Scotti',
    'Serafini', 'Serra', 'Silvestri', 'Simone', 'Simonetti', 'Sorrentino', 'Spada',
    'Spina', 'Stefani', 'Stella', 'Tedeschi', 'Tedesco', 'Testa', 'Tomassoni Compagnucci',
    'Tosi', 'Trevisan', 'Vaccaro', 'Valenti', 'Valentini', 'Ventura',
    'Venturini', 'Viganò', 'Villa', 'Villani', 'Vitale', 'Vitali', 'Volpe', 'Volpi',
    'Zambon Polfranceschi', 'Zambon Sagramoso', 'Zanella', 'Zanetti', 'Zanini'];

  /**
   * Lista dei formati per il generatore di numeri di telefono fittizi
   *
   * @var array $formatoTelefono Lista di formati
   */
  protected static $formatoTelefono = [
    '0## ### ###',
    '3## ### ###',
    '+39 0## ### ###',
    '+39 3## ### ###'
  ];


  //==================== METODI DELLA CLASSE ====================

  /**
   * Genera un nome fittizio (maschile o femminile)
   *
   * @param string $genere Indica il genere del nome fittizio da generare (M=maschile, F=femminile)
   *
   * @return string Il nome fittizio generato
   */
  public function nome(string $genere=null): string {
    $genere = ($genere == 'M' || $genere == 'F') ? $genere : static::randomElement(['M', 'F']);
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
   * @param string $genere Indica il sesso dell'utente (M=maschile, F=femminile)
   *
   * @return array La lista di nome, cognome e username generati
   */
  public function utente(string $genere): array {
    $nome = $this->nome($genere);
    $cognome = $this->cognome();
    $username = strtolower(str_replace([' ','\'','à','è','é','ì','ò','ù'], ['','','a','e','e','i','o','u'], $nome).
      '.'.str_replace([' ','\'','à','è','é','ì','ò','ù'], ['','','a','e','e','i','o','u'], $cognome));
    // restituisce i dati generati
    return array($nome, $cognome, $username);
  }

  /**
   * Genera numeri di telefono fittizi
   *
   * @param int $num Numero di recapiti telefonici da generare
   *
   * @return array Lista di numeri di telefono fittizi
   */
  public function telefono(int $num): array {
    $tel = array();
    for ($i = 0; $i < $num; $i++) {
      $tel[] = static::numerify($this->generator->parse(static::randomElement(static::$formatoTelefono)));
    }
    // restituisce i dati generati
    return $tel;
  }

}
