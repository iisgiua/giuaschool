<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;


/**
 * MessageType - tipo Message per i form (testo con filtro)
 */
class MessageType extends AbstractType {

  /**
   * Crea il tipo per il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
    public function buildForm(FormBuilderInterface $builder, array $options) {
      $builder->addModelTransformer(new CallbackTransformer(
          // converte nel formato testo semplice per l'editing
          function ($messaggio) {
            return strip_tags($messaggio);
          },
          // converte nel formato messaggio (testo con HTML) per la memorizzazione
          function ($testo) {
            // sanifica input
            $testoPulito = strip_tags($testo);
            return preg_replace('#\b(https?):(/?/?)([^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/)))#i',
              '<a href="$1://$3" target="_blank" title="Collegamento esterno">$1://$3</a>', $testoPulito);;
          }
        ));
    }

    /**
     * Configura le opzioni usate nel form
     *
     * @param OptionsResolver $resolver Gestore delle opzioni
     */
    public function configureOptions(OptionsResolver $resolver) {
    }

    /**
     * Restituisce la classe padre per il tipo Message
     *
     * @return string Classe padre
     */
    public function getParent() {
      return TextareaType::class;
    }

}
