<?php

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Mink\Session;


/**
 *
 */
final class CovidStaffContext extends BaseContext {



    /**
     * @Given utente staff
     */
    public function utenteStaff()
    {
$this->initDatabase();
      $this->login('raoul.martino', 'raoul.martino');


    }


/**
 * @When va a pagina :pag
 */
public function vaAPagina($pag)
{
  $this->goToPage($pag);

dump( "Status code: ". $this->session->getStatusCode());
dump( "Current URL: ". $this->session->getCurrentUrl());
//-- dump($this->kernel);

}




    /**
     * @Then visualizza alunni con procedura covid attiva
     */
    public function visualizzaAlunniConProceduraCovidAttiva()
    {
        throw new PendingException();
    }

    /**
     * @When alunno è presente in lista alunni con procedura covid attiva
     */
    public function alunnoEPresenteInListaAlunniConProceduraCovidAttiva()
    {
        throw new PendingException();
    }

    /**
     * @When clicca su pulsante :arg1 di alunno
     */
    public function cliccaSuPulsanteDiAlunno($arg1)
    {
        throw new PendingException();
    }

    /**
     * @Then inserisce annotazione su controllo certificato medico
     */
    public function inserisceAnnotazioneSuControlloCertificatoMedico()
    {
        throw new PendingException();
    }

    /**
     * @Then chiude procedura covid per alunno
     */
    public function chiudeProceduraCovidPerAlunno()
    {
        throw new PendingException();
    }

    /**
     * @When clicca su pulsante :arg1
     */
    public function cliccaSuPulsante($arg1)
    {
        throw new PendingException();
    }

    /**
     * @When sceglie alunno
     */
    public function sceglieAlunno()
    {
        throw new PendingException();
    }

    /**
     * @When inserisce ora uscita
     */
    public function inserisceOraUscita()
    {
        throw new PendingException();
    }

    /**
     * @Then imposta procedura covid per alunno attiva
     */
    public function impostaProceduraCovidPerAlunnoAttiva()
    {
        throw new PendingException();
    }

    /**
     * @Then inserisce avviso per genitori\/alunno
     */
    public function inserisceAvvisoPerGenitoriAlunno()
    {
        throw new PendingException();
    }

    /**
     * @Then inserisce avviso per docenti\/coordinatore
     */
    public function inserisceAvvisoPerDocentiCoordinatore()
    {
        throw new PendingException();
    }
}
